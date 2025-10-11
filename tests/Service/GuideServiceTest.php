<?php

declare(strict_types=1);

namespace Memex\Tests\Service;

use Memex\Service\GuideService;
use Memex\Service\PatternCompilerService;
use Memex\Service\VectorService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GuideServiceTest extends TestCase
{
    private string $testKbPath;
    private GuideService $service;

    protected function setUp(): void
    {
        $this->testKbPath = sys_get_temp_dir() . '/memex-test-' . uniqid();
        mkdir($this->testKbPath, 0755, true);
        mkdir($this->testKbPath . '/guides', 0755, true);
        
        $compiler = new PatternCompilerService();
        $vectorService = $this->createMock(VectorService::class);
        
        $this->service = new GuideService($this->testKbPath, $compiler, $vectorService);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemoveDirectory($this->testKbPath);
    }

    public function testWriteCreatesGuideFile(): void
    {
        $slug = $this->service->write('Test Guide', 'Content here', ['tag1', 'tag2']);
        
        $this->assertSame('test-guide', $slug);
        $this->assertFileExists($this->testKbPath . '/guides/test-guide.md');
    }

    public function testWriteThrowsOnEmptyTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Title contains invalid characters');
        
        $this->service->write('', 'Content');
    }

    public function testWriteThrowsOnInvalidTitleCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Title contains invalid characters');
        
        $this->service->write('Test@Guide#Invalid', 'Content');
    }

    public function testWriteThrowsOnTooLongTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Title too long');
        
        $this->service->write(str_repeat('a', 201), 'Content');
    }

    public function testWriteThrowsOnEmptyContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content cannot be empty');
        
        $this->service->write('Title', '');
    }

    public function testWriteThrowsOnTooLargeContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content too large');
        
        $this->service->write('Title', str_repeat('a', 1048577));
    }

    public function testWriteThrowsOnExistingFileWithoutOverwrite(): void
    {
        $this->service->write('Test Guide', 'Content');
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already exists');
        
        $this->service->write('Test Guide', 'New Content');
    }

    public function testWriteOverwritesExistingFileWithFlag(): void
    {
        $this->service->write('Test Guide', 'Content');
        $slug = $this->service->write('Test Guide', 'New Content', [], true);
        
        $this->assertSame('test-guide', $slug);
        $content = file_get_contents($this->testKbPath . '/guides/test-guide.md');
        $this->assertStringContainsString('New Content', $content);
        $this->assertStringContainsString('updated:', $content);
    }

    public function testDeleteRemovesGuideFile(): void
    {
        $this->service->write('Test Guide', 'Content');
        
        $result = $this->service->delete('test-guide');
        
        $this->assertTrue($result['success']);
        $this->assertSame('test-guide', $result['slug']);
        $this->assertSame('guide', $result['type']);
        $this->assertFileDoesNotExist($this->testKbPath . '/guides/test-guide.md');
    }

    public function testDeleteThrowsOnNonExistingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid file path');
        
        $this->service->delete('non-existing');
    }

    public function testDeleteThrowsOnPathTraversal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid slug format');
        
        $this->service->delete('../../../etc/passwd');
    }

    public function testSlugifyConvertsToLowerCase(): void
    {
        $slug = $this->service->write('TEST GUIDE', 'Content');
        $this->assertSame('test-guide', $slug);
    }

    public function testSlugifyRemovesSpecialCharacters(): void
    {
        $slug = $this->service->write('Test Guide 123', 'Content');
        $this->assertSame('test-guide-123', $slug);
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
