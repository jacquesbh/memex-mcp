<?php

declare(strict_types=1);

namespace Memex\Tests\Service;

use Memex\Service\ContextService;
use Memex\Service\PatternCompilerService;
use Memex\Service\VectorService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContextServiceTest extends TestCase
{
    private string $testKbPath;
    private ContextService $service;

    protected function setUp(): void
    {
        $this->testKbPath = sys_get_temp_dir() . '/memex-test-' . uniqid();
        mkdir($this->testKbPath, 0755, true);
        mkdir($this->testKbPath . '/contexts', 0755, true);
        
        $compiler = new PatternCompilerService();
        $vectorService = $this->createMock(VectorService::class);
        
        $this->service = new ContextService($this->testKbPath, $compiler, $vectorService);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemoveDirectory($this->testKbPath);
    }

    public function testWriteCreatesContextFile(): void
    {
        $slug = $this->service->write('Test Context', 'Context content', ['expert', 'sylius']);
        
        $this->assertSame('test-context', $slug);
        $this->assertFileExists($this->testKbPath . '/contexts/test-context.md');
    }

    public function testWriteGeneratesProperFrontmatter(): void
    {
        $this->service->write('Test Context', 'Content', ['tag1', 'tag2']);
        
        $content = file_get_contents($this->testKbPath . '/contexts/test-context.md');
        
        $this->assertStringContainsString('title: "Test Context"', $content);
        $this->assertStringContainsString('type: context', $content);
        $this->assertStringContainsString('tags: ["tag1", "tag2"]', $content);
        $this->assertStringContainsString('created:', $content);
    }

    public function testDeleteRemovesContextFile(): void
    {
        $this->service->write('Test Context', 'Content');
        
        $result = $this->service->delete('test-context');
        
        $this->assertTrue($result['success']);
        $this->assertSame('test-context', $result['slug']);
        $this->assertSame('context', $result['type']);
        $this->assertFileDoesNotExist($this->testKbPath . '/contexts/test-context.md');
    }

    public function testDeleteThrowsOnNonExistingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid file path');
        
        $this->service->delete('non-existing');
    }

    public function testWriteThrowsOnEmptyContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content cannot be empty');
        
        $this->service->write('Title', '   ');
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
