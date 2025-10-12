<?php

declare(strict_types=1);

namespace Memex\Tests\Service;

use Memex\Service\VectorService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class VectorServiceTest extends TestCase
{
    private string $tempDir;
    private VectorService $service;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/memex-vector-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        
        if (getenv('SKIP_OLLAMA_TESTS') === '1') {
            $this->markTestSkipped('Ollama tests skipped');
        }
        
        $this->service = new VectorService($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testConstructorCreatesVarDirectory(): void
    {
        $this->assertDirectoryExists($this->tempDir . '/.vectors');
    }

    public function testConstructorCreatesDatabaseFile(): void
    {
        $this->assertFileExists($this->tempDir . '/.vectors/embeddings.db');
    }

    public function testIndexStoresDocument(): void
    {
        $compiled = [
            'name' => 'Test Guide',
            'slug' => 'test-guide',
            'content' => 'This is test content',
            'sections' => [],
            'metadata' => [
                'type' => 'guide',
                'title' => 'Test Guide',
                'tags' => ['php', 'testing'],
                'created' => '2025-01-01'
            ]
        ];
        
        $this->service->index('test-guide', $compiled);
        
        $results = $this->service->listAll();
        $this->assertCount(1, $results);
        $this->assertSame('test-guide', $results[0]['slug']);
        $this->assertSame('Test Guide', $results[0]['name']);
    }

    public function testIndexStoresDocumentSections(): void
    {
        $compiled = [
            'name' => 'Guide with Sections',
            'slug' => 'sectioned-guide',
            'content' => 'Main content for PHP programming',
            'sections' => [
                ['title' => 'Section 1', 'content' => 'Content about PHP basics', 'level' => 2],
                ['title' => 'Section 2', 'content' => 'Content about PHP advanced', 'level' => 2],
            ],
            'metadata' => [
                'type' => 'guide',
                'title' => 'Guide with Sections',
                'tags' => []
            ]
        ];
        
        $this->service->index('sectioned-guide', $compiled);
        
        $allResults = $this->service->search('PHP', 10, 0.0);
        $sections = array_filter($allResults, fn($r) => $r['type'] === 'section');
        
        $this->assertCount(2, $sections);
    }

    public function testIndexSkipsEmptySections(): void
    {
        $compiled = [
            'name' => 'Guide',
            'slug' => 'guide',
            'content' => 'Main content about Symfony',
            'sections' => [
                ['title' => 'Section 1', 'content' => 'Content about Symfony framework', 'level' => 2],
                ['title' => 'Empty', 'content' => '   ', 'level' => 2],
            ],
            'metadata' => ['type' => 'guide', 'title' => 'Guide', 'tags' => []]
        ];
        
        $this->service->index('guide', $compiled);
        
        $allResults = $this->service->search('Symfony', 10, 0.0);
        $sections = array_filter($allResults, fn($r) => $r['type'] === 'section');
        
        $this->assertCount(1, $sections);
    }

    public function testSearchReturnsSimilarDocuments(): void
    {
        $this->service->index('doc1', [
            'name' => 'PHP Doc 1',
            'content' => 'Document about PHP programming and development',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'PHP Doc 1', 'tags' => []]
        ]);
        
        $this->service->index('doc2', [
            'name' => 'PHP Doc 2',
            'content' => 'Another PHP document for programming',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'PHP Doc 2', 'tags' => []]
        ]);
        
        $this->service->index('doc3', [
            'name' => 'JS Doc',
            'content' => 'JavaScript tutorial for web development',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'JS Doc', 'tags' => []]
        ]);
        
        $results = $this->service->search('PHP programming', 2, 0.3);
        
        $this->assertGreaterThan(0, count($results));
        $this->assertArrayHasKey('score', $results[0]);
        $this->assertArrayHasKey('slug', $results[0]);
    }

    public function testSearchRespectsLimit(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->service->index("doc{$i}", [
                'name' => "Doc {$i}",
                'content' => "Content {$i}",
                'sections' => [],
                'metadata' => ['type' => 'guide', 'title' => "Doc {$i}", 'tags' => []]
            ]);
        }
        
        $results = $this->service->search('Content', 3, 0.0);
        
        $this->assertLessThanOrEqual(3, count($results));
    }

    public function testSearchRespectsThreshold(): void
    {
        $this->service->index('similar', [
            'name' => 'Similar',
            'content' => 'PHP programming language documentation',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Similar', 'tags' => []]
        ]);
        
        $this->service->index('different', [
            'name' => 'Different',
            'content' => 'Quantum physics and advanced mathematics',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Different', 'tags' => []]
        ]);
        
        $highThreshold = $this->service->search('PHP programming', 10, 0.7);
        $lowThreshold = $this->service->search('PHP programming', 10, 0.1);
        
        $this->assertGreaterThanOrEqual(count($highThreshold), count($lowThreshold));
        if (!empty($highThreshold)) {
            $this->assertGreaterThanOrEqual(0.7, $highThreshold[0]['score']);
        }
    }

    public function testSearchReturnsSortedResults(): void
    {
        $this->service->index('php', [
            'name' => 'PHP',
            'content' => 'PHP programming language',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'PHP', 'tags' => []]
        ]);
        
        $this->service->index('python', [
            'name' => 'Python',
            'content' => 'Python programming language',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Python', 'tags' => []]
        ]);
        
        $this->service->index('javascript', [
            'name' => 'JavaScript',
            'content' => 'JavaScript programming language',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'JavaScript', 'tags' => []]
        ]);
        
        $results = $this->service->search('programming', 10, 0.0);
        
        $this->assertGreaterThanOrEqual(2, count($results));
        for ($i = 1; $i < count($results); $i++) {
            $this->assertGreaterThanOrEqual($results[$i]['score'], $results[$i-1]['score']);
        }
    }

    public function testListAllReturnsAllDocuments(): void
    {
        $this->service->index('guide1', [
            'name' => 'Guide 1',
            'content' => 'Content 1',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Guide 1', 'tags' => []]
        ]);
        
        $this->service->index('guide2', [
            'name' => 'Guide 2',
            'content' => 'Content 2',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Guide 2', 'tags' => []]
        ]);
        
        $this->service->index('context1', [
            'name' => 'Context 1',
            'content' => 'Content 3',
            'sections' => [],
            'metadata' => ['type' => 'context', 'title' => 'Context 1', 'tags' => []]
        ]);
        
        $results = $this->service->listAll();
        
        $this->assertCount(3, $results);
    }

    public function testListAllFiltersByType(): void
    {
        $this->service->index('guide1', [
            'name' => 'Guide 1',
            'content' => 'Content',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Guide 1', 'tags' => []]
        ]);
        
        $this->service->index('context1', [
            'name' => 'Context 1',
            'content' => 'Content',
            'sections' => [],
            'metadata' => ['type' => 'context', 'title' => 'Context 1', 'tags' => []]
        ]);
        
        $guides = $this->service->listAll('guide');
        $contexts = $this->service->listAll('context');
        
        $this->assertCount(1, $guides);
        $this->assertCount(1, $contexts);
        $this->assertSame('guide', $guides[0]['type']);
        $this->assertSame('context', $contexts[0]['type']);
    }

    public function testListAllExcludesSections(): void
    {
        $this->service->index('guide', [
            'name' => 'Guide',
            'content' => 'Main',
            'sections' => [
                ['title' => 'Section', 'content' => 'Section content', 'level' => 2]
            ],
            'metadata' => ['type' => 'guide', 'title' => 'Guide', 'tags' => []]
        ]);
        
        $results = $this->service->listAll();
        
        $this->assertCount(1, $results);
        $this->assertSame('guide', $results[0]['type']);
    }

    public function testDeleteRemovesDocumentAndSections(): void
    {
        $this->service->index('to-delete', [
            'name' => 'To Delete',
            'content' => 'Main content about Laravel',
            'sections' => [
                ['title' => 'Section 1', 'content' => 'Content about Laravel features', 'level' => 2],
            ],
            'metadata' => ['type' => 'guide', 'title' => 'To Delete', 'tags' => []]
        ]);
        
        $beforeDelete = $this->service->listAll();
        $this->assertCount(1, $beforeDelete);
        
        $this->service->delete('to-delete');
        
        $afterDelete = $this->service->listAll();
        $this->assertCount(0, $afterDelete);
        
        $allResults = $this->service->search('Laravel', 100, 0.0);
        $this->assertCount(0, $allResults);
    }

    public function testDeleteHandlesNonExistentDocument(): void
    {
        $this->service->delete('non-existent');
        
        $this->assertTrue(true);
    }

    public function testIndexUpdatesExistingDocument(): void
    {
        $this->service->index('update-test', [
            'name' => 'Original',
            'content' => 'Original content',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Original', 'tags' => []]
        ]);
        
        $this->service->index('update-test', [
            'name' => 'Updated',
            'content' => 'Updated content',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Updated', 'tags' => []]
        ]);
        
        $results = $this->service->listAll();
        $this->assertCount(1, $results);
        $this->assertSame('Updated', $results[0]['name']);
    }

    public function testSearchReturnsMetadataFields(): void
    {
        $this->service->index('test', [
            'name' => 'Test',
            'content' => 'Test content',
            'sections' => [],
            'metadata' => [
                'type' => 'guide',
                'title' => 'Test Guide',
                'tags' => ['tag1', 'tag2'],
                'created' => '2025-01-01'
            ]
        ]);
        
        $results = $this->service->search('Test', 1, 0.0);
        
        $this->assertCount(1, $results);
        $result = $results[0];
        
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('slug', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('tags', $result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('metadata', $result);
        
        $this->assertSame('test', $result['slug']);
        $this->assertSame('Test', $result['name']);
        $this->assertSame(['tag1', 'tag2'], $result['tags']);
    }

    public function testListAllOrdersByCreatedAtDesc(): void
    {
        $this->service->index('old', [
            'name' => 'Old',
            'content' => 'Content',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Old', 'tags' => [], 'created' => '2025-01-01']
        ]);
        
        sleep(1);
        
        $this->service->index('new', [
            'name' => 'New',
            'content' => 'Content',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'New', 'tags' => [], 'created' => '2025-01-02']
        ]);
        
        $results = $this->service->listAll();
        
        $this->assertSame('new', $results[0]['slug']);
        $this->assertSame('old', $results[1]['slug']);
    }
}
