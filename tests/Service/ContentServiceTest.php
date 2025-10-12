<?php

declare(strict_types=1);

namespace Memex\Tests\Service;

use InvalidArgumentException;
use Memex\Service\ContentService;
use Memex\Service\PatternCompilerService;
use Memex\Service\VectorService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
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

    public function testGetReturnsItemByUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $expectedMetadata = [
            'uuid' => $uuid,
            'slug' => 'test-item',
            'title' => 'Test Item'
        ];
        
        $this->vectorService->expects($this->once())
            ->method('getByUuid')
            ->with($uuid)
            ->willReturn([
                'uuid' => $uuid,
                'slug' => 'test-item',
                'metadata' => $expectedMetadata
            ]);
        
        $result = $this->service->get($uuid);
        
        $this->assertSame($expectedMetadata, $result);
    }

    public function testGetThrowsWhenUuidNotFound(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440001';
        
        $this->vectorService->method('getByUuid')->willReturn(null);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("test not found with UUID: {$uuid}");
        
        $this->service->get($uuid);
    }

    public function testGetThrowsOnInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID v4 format');
        
        $this->service->get('invalid-uuid');
    }

    public function testListReturnsFormattedItems(): void
    {
        $uuid1 = '550e8400-e29b-41d4-a716-446655440000';
        $uuid2 = '550e8400-e29b-41d4-a716-446655440001';
        
        $items = [
            [
                'uuid' => $uuid1,
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
                'uuid' => $uuid2,
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
        $this->assertSame($uuid1, $result[0]['uuid']);
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
            ->with('test-title', $this->anything(), ['name' => 'test', 'slug' => 'test-title']);
        
        $uuid = Uuid::v4()->toString();
        $result = $this->service->write($uuid, 'Test Title', 'Test content', ['tag1', 'tag2']);
        $this->assertSame('test-title', $result['slug']);
        $this->assertFileExists($this->tempDir . '/tests/test-title.md');
        
        $content = file_get_contents($this->tempDir . '/tests/test-title.md');
        $this->assertStringContainsString('title: Test Title', $content);
        $this->assertStringContainsString('type: test', $content);
        $this->assertStringContainsString('tags: [tag1, tag2]', $content);
        $this->assertStringContainsString('Test content', $content);
    }

    public function testWriteThrowsWhenFileExistsWithoutOverwrite(): void
    {
        $uuid = Uuid::v4()->toString();
        
        $this->vectorService->expects($this->exactly(2))
            ->method('getByUuid')
            ->with($uuid)
            ->willReturnOnConsecutiveCalls(
                null,
                ['uuid' => $uuid, 'slug' => 'existing', 'content' => 'Old']
            );
        
        $this->service->write($uuid, 'Existing', 'Content');
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Content with UUID {$uuid} already exists");
        
        $this->service->write($uuid, 'Existing', 'New content');
    }

    public function testWriteOverwritesWithFlag(): void
    {
        $uuid = Uuid::v4()->toString();
        
        $this->vectorService->expects($this->exactly(2))
            ->method('getByUuid')
            ->with($uuid)
            ->willReturnOnConsecutiveCalls(
                null,
                ['uuid' => $uuid, 'slug' => 'existing', 'content' => 'Old']
            );
        
        mkdir($this->tempDir . '/tests', 0755, true);
        $this->service->write($uuid, 'Existing', 'Old content');
        $result = $this->service->write($uuid, 'Existing', 'New content', [], true);
        
        $this->assertSame('existing', $result['slug']);
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
        
        $uuid = Uuid::v4()->toString();
        $this->service->write($uuid, 'Title with <script>', 'content');
    }

    public function testValidateTitleThrowsOnTooLong(): void
    {
        $longTitle = str_repeat('a', 201);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('too long');
        
        $uuid = Uuid::v4()->toString();
        $this->service->write($uuid, $longTitle, 'content');
    }

    public function testValidateTitleThrowsOnEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');
        
        $uuid = Uuid::v4()->toString();
        $this->service->write($uuid, '   ', 'content');
    }

    public function testValidateContentThrowsOnTooLarge(): void
    {
        $largeContent = str_repeat('x', 1048577);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('too large');
        
        $uuid = Uuid::v4()->toString();
        $this->service->write($uuid, 'Title', $largeContent);
    }

    public function testValidateContentThrowsOnEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');
        
        $uuid = Uuid::v4()->toString();
        $this->service->write($uuid, 'Title', '   ');
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
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildFrontmatter');
        $method->setAccessible(true);
        
        $frontmatter = $method->invoke($this->service, $uuid, 'Test Title', ['tag1', 'tag2'], false);
        
        $this->assertStringContainsString("uuid: {$uuid}", $frontmatter);
        $this->assertStringContainsString('title: Test Title', $frontmatter);
        $this->assertStringContainsString('type: test', $frontmatter);
        $this->assertStringContainsString('tags: [tag1, tag2]', $frontmatter);
        $this->assertStringContainsString('created:', $frontmatter);
        $this->assertStringNotContainsString('updated:', $frontmatter);
    }

    public function testBuildFrontmatterForUpdate(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440001';
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildFrontmatter');
        $method->setAccessible(true);
        
        $frontmatter = $method->invoke($this->service, $uuid, 'Updated Title', [], true);
        
        $this->assertStringContainsString("uuid: {$uuid}", $frontmatter);
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

    public function testReindexAllIndexesAllFiles(): void
    {
        mkdir($this->tempDir . '/tests', 0755, true);
        $uuid1 = '550e8400-e29b-41d4-a716-446655440000';
        $uuid2 = '550e8400-e29b-41d4-a716-446655440001';
        $uuid3 = '550e8400-e29b-41d4-a716-446655440002';
        
        $now = date('Y-m-d');
        file_put_contents($this->tempDir . '/tests/file1.md', "---\nuuid: {$uuid1}\ntitle: File 1\ntype: test\ncreated: {$now}\n---\nContent 1");
        file_put_contents($this->tempDir . '/tests/file2.md', "---\nuuid: {$uuid2}\ntitle: File 2\ntype: test\ncreated: {$now}\n---\nContent 2");
        file_put_contents($this->tempDir . '/tests/file3.md', "---\nuuid: {$uuid3}\ntitle: File 3\ntype: test\ncreated: {$now}\n---\nContent 3");
        
        $this->compilerService->expects($this->exactly(3))
            ->method('compile')
            ->willReturnCallback(fn($content, $filename) => [
                'name' => $filename,
                'slug' => str_replace('.md', '', $filename),
                'metadata' => [
                    'uuid' => $filename === 'file1.md' ? $uuid1 : ($filename === 'file2.md' ? $uuid2 : $uuid3)
                ]
            ]);
        
        $this->vectorService->expects($this->exactly(3))
            ->method('index');
        
        $count = $this->service->reindexAll();
        
        $this->assertSame(3, $count);
    }

    public function testReindexAllReturnsZeroWhenDirDoesNotExist(): void
    {
        $count = $this->service->reindexAll();
        
        $this->assertSame(0, $count);
    }

    public function testReindexAllWithOnlyNewSkipsExisting(): void
    {
        mkdir($this->tempDir . '/tests', 0755, true);
        $uuidExisting = '550e8400-e29b-41d4-a716-446655440000';
        $uuidNew = '550e8400-e29b-41d4-a716-446655440001';
        
        $now = date('Y-m-d');
        $existingContent = "---\nuuid: {$uuidExisting}\ntitle: Existing\ntype: test\ncreated: {$now}\n---\nExisting content";
        $newContent = "---\nuuid: {$uuidNew}\ntitle: New\ntype: test\ncreated: {$now}\n---\nNew content";
        
        file_put_contents($this->tempDir . '/tests/existing.md', $existingContent);
        file_put_contents($this->tempDir . '/tests/new.md', $newContent);
        
        $this->vectorService->expects($this->exactly(2))
            ->method('existsByUuid')
            ->willReturnMap([
                [$uuidExisting, true],
                [$uuidNew, false]
            ]);
        
        $this->compilerService->expects($this->exactly(2))
            ->method('compile')
            ->willReturnCallback(function($content, $filename) use ($uuidExisting, $uuidNew) {
                $uuid = $filename === 'existing.md' ? $uuidExisting : $uuidNew;
                return [
                    'name' => str_replace('.md', '', $filename),
                    'slug' => str_replace('.md', '', $filename),
                    'metadata' => ['uuid' => $uuid]
                ];
            });
        
        $this->vectorService->expects($this->once())
            ->method('index')
            ->with('new', $uuidNew, $this->anything());
        
        $count = $this->service->reindexAll(true);
        
        $this->assertSame(1, $count);
    }

    public function testReindexAllWithOnlyNewIndexesAllWhenNoneExist(): void
    {
        mkdir($this->tempDir . '/tests', 0755, true);
        $uuid1 = '550e8400-e29b-41d4-a716-446655440000';
        $uuid2 = '550e8400-e29b-41d4-a716-446655440001';
        
        $now = date('Y-m-d');
        file_put_contents($this->tempDir . '/tests/new1.md', "---\nuuid: {$uuid1}\ntitle: New 1\ntype: test\ncreated: {$now}\n---\nContent 1");
        file_put_contents($this->tempDir . '/tests/new2.md', "---\nuuid: {$uuid2}\ntitle: New 2\ntype: test\ncreated: {$now}\n---\nContent 2");
        
        $this->vectorService->method('existsByUuid')->willReturn(false);
        
        $this->compilerService->expects($this->exactly(2))
            ->method('compile')
            ->willReturnCallback(fn($content, $filename) => [
                'name' => $filename,
                'slug' => str_replace('.md', '', $filename),
                'metadata' => [
                    'uuid' => $filename === 'new1.md' ? $uuid1 : $uuid2
                ]
            ]);
        
        $this->vectorService->expects($this->exactly(2))
            ->method('index');
        
        $count = $this->service->reindexAll(true);
        
        $this->assertSame(2, $count);
    }
}
