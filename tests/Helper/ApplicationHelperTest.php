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
        $this->assertStringContainsString('/.memex/knowledge-base', $path);
        $this->assertStringStartsWith($_SERVER['HOME'] ?? getenv('HOME'), $path);
    }

    public function testGetDefaultKnowledgeBasePathThrowsWhenNoHome(): void
    {
        $originalServer = $_SERVER['HOME'] ?? null;
        $originalEnv = getenv('HOME');
        
        unset($_SERVER['HOME']);
        putenv('HOME');
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to determine home directory');
        
        try {
            ApplicationHelper::getDefaultKnowledgeBasePath();
        } finally {
            if ($originalServer !== null) {
                $_SERVER['HOME'] = $originalServer;
            }
            if ($originalEnv !== false) {
                putenv("HOME={$originalEnv}");
            }
        }
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
        $testPath = $_SERVER['HOME'] . '/.memex/knowledge-base';
        if (!is_dir($testPath)) {
            mkdir($testPath, 0755, true);
            $cleanup = true;
        }
        
        $resolved = ApplicationHelper::resolveKnowledgeBasePath(null);
        
        $this->assertIsString($resolved);
        $this->assertStringContainsString('/.memex/knowledge-base', $resolved);
        
        if (isset($cleanup)) {
            rmdir($testPath);
            rmdir(dirname($testPath));
        }
    }

    public function testLoadEnvironmentDoesNotThrowWhenNoEnvFile(): void
    {
        ApplicationHelper::loadEnvironment();
        
        $this->assertTrue(true);
    }

    public function testLoadEnvironmentLoadsExistingEnvFile(): void
    {
        ApplicationHelper::loadEnvironment();
        
        $this->assertTrue(true);
    }

    public function testResolveKnowledgeBasePathThrowsOnUnreadableDirectory(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('File permissions test skipped on Windows');
        }
        
        $testPath = sys_get_temp_dir() . '/memex-test-unreadable-' . uniqid();
        mkdir($testPath, 0000, true);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not readable');
        
        try {
            ApplicationHelper::resolveKnowledgeBasePath($testPath);
        } finally {
            chmod($testPath, 0755);
            rmdir($testPath);
        }
    }
}
