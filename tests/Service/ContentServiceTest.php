<?php

declare(strict_types=1);

namespace Memex\Tests\Service;

use InvalidArgumentException;
use Memex\Service\ContentService;
use Memex\Service\PatternCompilerService;
use Memex\Service\VectorService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContentServiceTest extends TestCase
{
    private string $tempDir;
    private ContentService $service;
    private VectorService $vectorService;
    private PatternCompilerService $compilerService;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/memex-content-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        
        $this->vectorService = $this->createMock(VectorService::class);
        $this->compilerService = $this->createMock(PatternCompilerService::class);
        
        $this->service = new class($this->tempDir, $this->compilerService, $this->vectorService) extends ContentService {
            protected function getContentType(): string { return 'test'; }
            protected function getContentDir(): string { return 'tests'; }
        };
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

    public function testGetReturnsItemMetadata(): void
    {
        $expectedMetadata = [
            'slug' => 'test-item',
            'title' => 'Test Item',
            'metadata' => ['created' => '2025-01-01']
        ];
        
        $this->vectorService->expects($this->once())
            ->method('search')
            ->with('test query', 1, 0.6)
            ->willReturn([
                [
                    'type' => 'item',
                    'slug' => 'test-item',
                    'metadata' => $expectedMetadata
                ]
            ]);
        
        $result = $this->service->get('test query');
        
        $this->assertSame($expectedMetadata, $result);
    }

    public function testGetThrowsWhenNoResults(): void
    {
        $this->vectorService->method('search')->willReturn([]);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('test not found: no results');
        
        $this->service->get('no results');
    }

    public function testGetHandlesSectionWithParent(): void
    {
        $parentMetadata = [
            'slug' => 'parent-item',
            'title' => 'Parent Item'
        ];
        
        $this->vectorService->expects($this->once())
            ->method('search')
            ->willReturn([
                [
                    'type' => 'section',
                    'slug' => 'section-item',
                    'metadata' => [
                        'parent_slug' => 'parent-item'
                    ]
                ]
            ]);
        
        $this->vectorService->expects($this->once())
            ->method('listAll')
            ->with('test')
            ->willReturn([
                [
                    'slug' => 'parent-item',
                    'metadata' => $parentMetadata
                ]
            ]);
        
        $result = $this->service->get('section query');
        
        $this->assertSame($parentMetadata, $result);
    }

    public function testGetHandlesSectionWithoutParentInList(): void
    {
        $sectionMetadata = [
            'slug' => 'section-item',
            'parent_slug' => 'parent-item',
            'title' => 'Section'
        ];
        
        $this->vectorService->expects($this->once())
            ->method('search')
            ->willReturn([
                [
                    'type' => 'section',
                    'slug' => 'section-item',
                    'metadata' => $sectionMetadata
                ]
            ]);
        
        $this->vectorService->expects($this->once())
            ->method('listAll')
            ->with('test')
            ->willReturn([
                [
                    'slug' => 'different-item',
                    'metadata' => ['title' => 'Different']
                ]
            ]);
        
        $result = $this->service->get('section query');
        
        $this->assertSame($sectionMetadata, $result);
    }

    public function testListReturnsFormattedItems(): void
    {
        $items = [
            [
                'slug' => 'item-1',
                'name' => 'Item 1',
                'title' => 'First Item',
                'tags' => ['tag1'],
                'metadata' => [
                    'metadata' => [
                        'created' => '2025-01-01',
                        'updated' => '2025-01-02'
                    ]
                ]
            ],
            [
                'slug' => 'item-2',
                'name' => 'Item 2',
                'title' => 'Second Item',
                'tags' => ['tag2'],
                'metadata' => []
            ]
        ];
        
        $this->vectorService->expects($this->once())
            ->method('listAll')
            ->with('test')
            ->willReturn($items);
        
        $result = $this->service->list();
        
        $this->assertCount(2, $result);
        $this->assertSame('item-1', $result[0]['slug']);
        $this->assertSame('Item 1', $result[0]['name']);
        $this->assertSame('2025-01-01', $result[0]['created']);
        $this->assertSame('2025-01-02', $result[0]['updated']);
        $this->assertNull($result[1]['created']);
    }

    public function testSearchDelegatesToVectorService(): void
    {
        $expectedResults = [
            ['slug' => 'result-1', 'score' => 0.95],
            ['slug' => 'result-2', 'score' => 0.85]
        ];
        
        $this->vectorService->expects($this->once())
            ->method('search')
            ->with('search query', 10)
            ->willReturn($expectedResults);
        
        $result = $this->service->search('search query', 10);
        
        $this->assertSame($expectedResults, $result);
    }

    public function testWriteCreatesNewFile(): void
    {
        $this->compilerService->method('compile')
            ->willReturn(['name' => 'test', 'slug' => 'test-title']);
        
        $this->vectorService->expects($this->once())
            ->method('index')
            ->with('test-title', ['name' => 'test', 'slug' => 'test-title']);
        
        $slug = $this->service->write('Test Title', 'Test content', ['tag1', 'tag2']);
        
        $this->assertSame('test-title', $slug);
        $this->assertFileExists($this->tempDir . '/tests/test-title.md');
        
        $content = file_get_contents($this->tempDir . '/tests/test-title.md');
        $this->assertStringContainsString('title: "Test Title"', $content);
        $this->assertStringContainsString('type: test', $content);
        $this->assertStringContainsString('tags: ["tag1", "tag2"]', $content);
        $this->assertStringContainsString('Test content', $content);
    }

    public function testWriteThrowsWhenFileExistsWithoutOverwrite(): void
    {
        mkdir($this->tempDir . '/tests', 0755, true);
        file_put_contents($this->tempDir . '/tests/existing.md', 'content');
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('test already exists: existing');
        
        $this->service->write('Existing', 'New content');
    }

    public function testWriteOverwritesWithFlag(): void
    {
        mkdir($this->tempDir . '/tests', 0755, true);
        file_put_contents($this->tempDir . '/tests/existing.md', '---
title: "Old"
created: 2025-01-01
---
Old content');
        
        $this->compilerService->method('compile')
            ->willReturn(['name' => 'test', 'slug' => 'existing']);
        
        $this->vectorService->expects($this->once())->method('index');
        
        $slug = $this->service->write('Existing', 'New content', [], true);
        
        $this->assertSame('existing', $slug);
        $content = file_get_contents($this->tempDir . '/tests/existing.md');
        $this->assertStringContainsString('New content', $content);
        $this->assertStringContainsString('updated:', $content);
    }

    public function testDeleteRemovesFileAndIndexes(): void
    {
        mkdir($this->tempDir . '/tests', 0755, true);
        file_put_contents($this->tempDir . '/tests/to-delete.md', '---
title: "Delete Me"
---
Content');
        
        $this->compilerService->method('compile')
            ->willReturn(['name' => 'Delete Me', 'metadata' => ['title' => 'Delete Me']]);
        
        $this->vectorService->expects($this->once())
            ->method('delete')
            ->with('to-delete');
        
        $result = $this->service->delete('to-delete');
        
        $this->assertTrue($result['success']);
        $this->assertSame('to-delete', $result['slug']);
        $this->assertSame('Delete Me', $result['title']);
        $this->assertFileDoesNotExist($this->tempDir . '/tests/to-delete.md');
    }

    public function testDeleteThrowsOnNonExistentFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid file path for test: missing');
        
        $this->service->delete('missing');
    }

    public function testDeleteThrowsWhenFileDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid file path for test: nonexistent-file');
        
        $this->service->delete('nonexistent-file');
    }

    public function testDeleteThrowsOnPathTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid slug format');
        
        $this->service->delete('../../../etc/passwd');
    }

    public function testValidateTitleThrowsOnInvalidCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid characters');
        
        $this->service->write('Title with <script>', 'content');
    }

    public function testValidateTitleThrowsOnTooLong(): void
    {
        $longTitle = str_repeat('a', 201);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('too long');
        
        $this->service->write($longTitle, 'content');
    }

    public function testValidateTitleThrowsOnEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');
        
        $this->service->write('   ', 'content');
    }

    public function testValidateContentThrowsOnTooLarge(): void
    {
        $largeContent = str_repeat('x', 1048577);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('too large');
        
        $this->service->write('Title', $largeContent);
    }

    public function testValidateContentThrowsOnEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');
        
        $this->service->write('Title', '   ');
    }

    public function testValidateSlugThrowsOnInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid slug format');
        
        $this->service->delete('INVALID SLUG');
    }

    public function testValidateSlugThrowsOnPathTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid slug format');
        
        $this->service->delete('../parent');
    }

    public function testValidateSlugThrowsOnPathTraversalWithDoubleDots(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid slug format');
        
        $this->service->delete('..');
    }

    public function testValidateSlugThrowsOnSlash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid slug format');
        
        $this->service->delete('path/to/file');
    }

    public function testSlugifyConvertsToLowerKebabCase(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('slugify');
        $method->setAccessible(true);
        
        $slug = $method->invoke($this->service, 'My Test Title');
        $this->assertSame('my-test-title', $slug);
        
        $slug = $method->invoke($this->service, 'Title!!With@@Special##Chars');
        $this->assertSame('title-with-special-chars', $slug);
    }

    public function testBuildFrontmatterForNewItem(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildFrontmatter');
        $method->setAccessible(true);
        
        $frontmatter = $method->invoke($this->service, 'Test Title', ['tag1', 'tag2'], false);
        
        $this->assertStringContainsString('title: "Test Title"', $frontmatter);
        $this->assertStringContainsString('type: test', $frontmatter);
        $this->assertStringContainsString('tags: ["tag1", "tag2"]', $frontmatter);
        $this->assertStringContainsString('created:', $frontmatter);
        $this->assertStringNotContainsString('updated:', $frontmatter);
    }

    public function testBuildFrontmatterForUpdate(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildFrontmatter');
        $method->setAccessible(true);
        
        $frontmatter = $method->invoke($this->service, 'Updated Title', [], true);
        
        $this->assertStringContainsString('updated:', $frontmatter);
        $this->assertStringNotContainsString('created:', $frontmatter);
    }

    public function testExtractSlugRemovesMdExtension(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractSlug');
        $method->setAccessible(true);
        
        $slug = $method->invoke($this->service, 'my-file.md');
        $this->assertSame('my-file', $slug);
    }

    public function testGetFullContentDir(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getFullContentDir');
        $method->setAccessible(true);
        
        $dir = $method->invoke($this->service);
        $this->assertSame($this->tempDir . '/tests', $dir);
    }
}
