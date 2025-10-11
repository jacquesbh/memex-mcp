<?php

declare(strict_types=1);

namespace Memex\Tests\Helper;

use Memex\Helper\ApplicationHelper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApplicationHelperTest extends TestCase
{
    public function testGetDefaultKnowledgeBasePathReturnsValidPath(): void
    {
        $path = ApplicationHelper::getDefaultKnowledgeBasePath();
        
        $this->assertIsString($path);
        $this->assertStringContainsString('memex-knowledge-base', $path);
    }

    public function testResolveKnowledgeBasePathAcceptsValidDirectory(): void
    {
        $testPath = sys_get_temp_dir() . '/memex-test-' . uniqid();
        mkdir($testPath, 0755, true);
        
        $resolved = ApplicationHelper::resolveKnowledgeBasePath($testPath);
        
        $this->assertSame(realpath($testPath), $resolved);
        
        rmdir($testPath);
    }

    public function testResolveKnowledgeBasePathThrowsOnNonExistentPath(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not exist');
        
        ApplicationHelper::resolveKnowledgeBasePath('/non/existent/path');
    }

    public function testResolveKnowledgeBasePathThrowsOnFile(): void
    {
        $testFile = sys_get_temp_dir() . '/memex-test-file-' . uniqid();
        touch($testFile);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not a directory');
        
        try {
            ApplicationHelper::resolveKnowledgeBasePath($testFile);
        } finally {
            unlink($testFile);
        }
    }

    public function testResolveKnowledgeBasePathUsesDefaultWhenNull(): void
    {
        $resolved = ApplicationHelper::resolveKnowledgeBasePath(null);
        
        $this->assertIsString($resolved);
        $this->assertStringContainsString('memex-knowledge-base', $resolved);
    }

    public function testLoadEnvironmentDoesNotThrowWhenNoEnvFile(): void
    {
        ApplicationHelper::loadEnvironment();
        
        $this->assertTrue(true);
    }
}
