<?php

declare(strict_types=1);

namespace Memex\Tests\Service;

use Memex\Service\VectorService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;

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

        $chunker = new TextSplitTransformer(700, 200);
        $this->service = new VectorService($this->tempDir, $chunker, 512);
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
                'uuid' => '00000000-0000-4000-8000-000000000001',
                'type' => 'guide',
                'title' => 'Test Guide',
                'tags' => ['php', 'testing'],
                'created' => '2025-01-01'
            ]
        ];
        
        $this->service->index('test-guide', '87aacdf4-0000-4000-8000-26180b93b1f9', $compiled);
        
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
                'uuid' => '00000000-0000-4000-8000-000000000001',
                'type' => 'guide',
                'title' => 'Guide with Sections',
                'tags' => []
            ]
        ];
        
        $this->service->index('sectioned-guide', '935113f2-0000-4000-8000-b5083154019d', $compiled);

        $allResults = $this->service->search('PHP', 10, 0.0, false);
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
            'metadata' => ['uuid' => '00000000-0000-4000-8000-000000000001',
                'type' => 'guide', 'title' => 'Guide', 'tags' => []]
        ];
        
        $this->service->index('guide', 'ca9ec735-0000-4000-8000-a0c391dc49c4', $compiled);

        $allResults = $this->service->search('Symfony', 10, 0.0, false);
        $sections = array_filter($allResults, fn($r) => $r['type'] === 'section');
        
        $this->assertCount(1, $sections);
    }

    public function testSearchReturnsSimilarDocuments(): void
    {
        $this->service->index('doc1', 'c9850b0b-0000-4000-8000-83e4b1789306', [
            'name' => 'PHP Doc 1',
            'content' => 'Document about PHP programming and development',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'PHP Doc 1', 'tags' => []]
        ]);
        
        $this->service->index('doc2', '508c5ab1-0000-4000-8000-271559ec2526', [
            'name' => 'PHP Doc 2',
            'content' => 'Another PHP document for programming',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'PHP Doc 2', 'tags' => []]
        ]);
        
        $this->service->index('doc3', '278b6a27-0000-4000-8000-af75c71ac23e', [
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
            $this->service->index("doc{$i}", "dc541354-0000-4000-8000-5605587157f7", [
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
        $this->service->index('similar', 'cbbb389d-0000-4000-8000-10faae554c64', [
            'name' => 'Similar',
            'content' => 'PHP programming language documentation',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Similar', 'tags' => []]
        ]);
        
        $this->service->index('different', '6c0780e3-0000-4000-8000-29e4b66fa807', [
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
        $this->service->index('php', '569121d1-0000-4000-8000-e1bfd762321e', [
            'name' => 'PHP',
            'content' => 'PHP programming language',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'PHP', 'tags' => []]
        ]);
        
        $this->service->index('python', 'a4d4b8b8-0000-4000-8000-23eeeb4347bd', [
            'name' => 'Python',
            'content' => 'Python programming language',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Python', 'tags' => []]
        ]);
        
        $this->service->index('javascript', '84ea90f7-0000-4000-8000-de9b9ed78d7e', [
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
        $this->service->index('guide1', 'd5a5b553-0000-4000-8000-f510e23d073d', [
            'name' => 'Guide 1',
            'content' => 'Content 1',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Guide 1', 'tags' => []]
        ]);
        
        $this->service->index('guide2', '4cace4e9-0000-4000-8000-f6ea0da319e7', [
            'name' => 'Guide 2',
            'content' => 'Content 2',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Guide 2', 'tags' => []]
        ]);
        
        $this->service->index('context1', '3483ee09-0000-4000-8000-14c1126e07fa', [
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
        $this->service->index('guide1', 'd5a5b553-0000-4000-8000-f510e23d073d', [
            'name' => 'Guide 1',
            'content' => 'Content',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Guide 1', 'tags' => []]
        ]);
        
        $this->service->index('context1', '3483ee09-0000-4000-8000-14c1126e07fa', [
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
        $this->service->index('guide', 'ca9ec735-0000-4000-8000-a0c391dc49c4', [
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
        $this->service->index('to-delete', '6737b118-0000-4000-8000-c134bdc40d21', [
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
        $this->service->index('update-test', '8f019a3c-0000-4000-8000-fd5b783b0dff', [
            'name' => 'Original',
            'content' => 'Original content',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Original', 'tags' => []]
        ]);
        
        $this->service->index('update-test', '8f019a3c-0000-4000-8000-fd5b783b0dff', [
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
        $this->service->index('test', 'd87f7e0c-0000-4000-8000-098f6bcd4621', [
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
        $this->service->index('old', '3f5dd4e5-0000-4000-8000-149603e6c035', [
            'name' => 'Old',
            'content' => 'Content',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Old', 'tags' => [], 'created' => '2025-01-01']
        ]);
        
        sleep(1);
        
        $this->service->index('new', '6be34445-0000-4000-8000-22af645d1859', [
            'name' => 'New',
            'content' => 'Content',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'New', 'tags' => [], 'created' => '2025-01-02']
        ]);
        
        $results = $this->service->listAll();
        
        $this->assertSame('new', $results[0]['slug']);
        $this->assertSame('old', $results[1]['slug']);
    }

    public function testExistsReturnsTrueForExistingDocument(): void
    {
        $this->service->index('exists-test', '287e2e84-0000-4000-8000-5c26b41580bd', [
            'name' => 'Exists Test',
            'content' => 'Content',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Exists Test', 'tags' => []]
        ]);
        
        $exists = $this->service->exists('exists-test');
        
        $this->assertTrue($exists);
    }

    public function testExistsReturnsFalseForNonExistentDocument(): void
    {
        $exists = $this->service->exists('non-existent');
        
        $this->assertFalse($exists);
    }

    public function testExistsIgnoresSections(): void
    {
        $this->service->index('with-sections', 'ccc9f637-0000-4000-8000-993638a612c6', [
            'name' => 'With Sections',
            'content' => 'Main content',
            'sections' => [
                ['title' => 'Section', 'content' => 'Section content', 'level' => 2]
            ],
            'metadata' => ['type' => 'guide', 'title' => 'With Sections', 'tags' => []]
        ]);
        
        $exists = $this->service->exists('with-sections');
        $this->assertTrue($exists);
        
        $sectionExists = $this->service->exists('with-sections_section_0');
        $this->assertFalse($sectionExists);
    }

    public function testChunkingLargeSection(): void
    {
        $largeContent = str_repeat('This is a test sentence about PHP programming. ', 200);

        $compiled = [
            'name' => 'Large Document',
            'slug' => 'large-doc',
            'content' => 'Short intro',
            'sections' => [
                ['title' => 'Large Section', 'content' => $largeContent, 'level' => 2]
            ],
            'metadata' => ['uuid' => '00000000-0000-4000-8000-000000000001',
                'type' => 'guide', 'title' => 'Large Document', 'tags' => []]
        ];

        $this->service->index('large-doc', '54939373-0000-4000-8000-84c63000e801', $compiled);

        $allResults = $this->service->search('PHP', 100, 0.0, false);
        $chunks = array_filter($allResults, fn($r) => $r['type'] === 'chunk');

        $this->assertGreaterThan(0, count($chunks), 'Large section should be split into chunks');

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(700, mb_strlen($chunk['content']), 'Each chunk should be <= 700 chars');
        }
    }

    public function testSmallSectionNotChunked(): void
    {
        $smallContent = 'This is a small section content.';

        $compiled = [
            'name' => 'Small Document',
            'slug' => 'small-doc',
            'content' => 'Short intro',
            'sections' => [
                ['title' => 'Small Section', 'content' => $smallContent, 'level' => 2]
            ],
            'metadata' => ['uuid' => '00000000-0000-4000-8000-000000000001',
                'type' => 'guide', 'title' => 'Small Document', 'tags' => []]
        ];

        $this->service->index('small-doc', '41a41f7d-0000-4000-8000-80e221d255bb', $compiled);

        $allResults = $this->service->search('small', 100, 0.0, false);
        $chunks = array_filter($allResults, fn($r) => $r['type'] === 'chunk');
        $sections = array_filter($allResults, fn($r) => $r['type'] === 'section');

        $this->assertCount(0, $chunks, 'Small section should not be chunked');
        $this->assertCount(1, $sections, 'Small section should be stored as section');
    }

    public function testChunkOverlap(): void
    {
        $content = str_repeat('ABCDEFGHIJ', 500);

        $compiled = [
            'name' => 'Overlap Test',
            'slug' => 'overlap-test',
            'content' => 'Short',
            'sections' => [
                ['title' => 'Section', 'content' => $content, 'level' => 2]
            ],
            'metadata' => ['uuid' => '00000000-0000-4000-8000-000000000001',
                'type' => 'guide', 'title' => 'Overlap Test', 'tags' => []]
        ];

        $this->service->index('overlap-test', 'bfd23c00-0000-4000-8000-e7926e651739', $compiled);

        $allResults = $this->service->search('ABCDEFGHIJ', 100, 0.0, false);
        $chunks = array_filter($allResults, fn($r) => $r['type'] === 'chunk');

        $this->assertGreaterThanOrEqual(2, count($chunks), 'Content should be split into multiple chunks');
    }

    public function testGetByUuidReturnsDocument(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->service->index('test-doc', $uuid, [
            'name' => 'Test Document',
            'content' => 'Test content',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Test Document', 'tags' => ['test']]
        ]);

        $result = $this->service->getByUuid($uuid);

        $this->assertNotNull($result);
        $this->assertSame($uuid, $result['uuid']);
        $this->assertSame('test-doc', $result['slug']);
    }

    public function testGetByUuidReturnsNullForNonExistent(): void
    {
        $result = $this->service->getByUuid('550e8400-e29b-41d4-a716-446655440999');

        $this->assertNull($result);
    }

    public function testExistsByUuidReturnsTrueForExisting(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440001';
        $this->service->index('doc', $uuid, [
            'name' => 'Doc',
            'content' => 'Content',
            'sections' => [],
            'metadata' => ['type' => 'guide', 'title' => 'Doc', 'tags' => []]
        ]);

        $this->assertTrue($this->service->existsByUuid($uuid));
    }

    public function testExistsByUuidReturnsFalseForNonExistent(): void
    {
        $this->assertFalse($this->service->existsByUuid('550e8400-e29b-41d4-a716-446655440888'));
    }
}
