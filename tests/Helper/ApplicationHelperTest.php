<?php

declare(strict_types=1);

namespace Memex\Tests\Helper;

use Memex\Exception\KnowledgeBaseNotDirectoryException;
use Memex\Exception\KnowledgeBaseNotFoundException;
use Memex\Exception\KnowledgeBaseNotReadableException;
use Memex\Helper\ApplicationHelper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApplicationHelperTest extends TestCase
{
    private string $tempDir;
    private ?string $originalCwd = null;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/memex-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        
        $this->originalCwd = getcwd();
        if ($this->originalCwd !== false) {
            chdir($this->tempDir);
        }
    }

    protected function tearDown(): void
    {
        if ($this->originalCwd !== null && $this->originalCwd !== false) {
            chdir($this->originalCwd);
        }
        
        $this->cleanupDirectory($this->tempDir);
    }

    private function cleanupDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->cleanupDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

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
        $this->expectException(KnowledgeBaseNotFoundException::class);
        $this->expectExceptionMessage('does not exist');
        
        try {
            ApplicationHelper::resolveKnowledgeBasePath('/non/existent/path');
        } catch (KnowledgeBaseNotFoundException $e) {
            $this->assertSame('/non/existent/path', $e->realPath);
            throw $e;
        }
    }

    public function testResolveKnowledgeBasePathThrowsOnFile(): void
    {
        $testFile = sys_get_temp_dir() . '/memex-test-file-' . uniqid();
        touch($testFile);
        
        $this->expectException(KnowledgeBaseNotDirectoryException::class);
        $this->expectExceptionMessage('not a directory');
        
        try {
            ApplicationHelper::resolveKnowledgeBasePath($testFile);
        } catch (KnowledgeBaseNotDirectoryException $e) {
            $this->assertSame(realpath($testFile), $e->realPath);
            throw $e;
        } finally {
            unlink($testFile);
        }
    }

    public function testResolveKnowledgeBasePathUsesDefaultWhenNull(): void
    {
        $originalHome = $_SERVER['HOME'] ?? getenv('HOME');
        
        $fakeHome = sys_get_temp_dir() . '/memex-fake-home-' . uniqid();
        mkdir($fakeHome, 0755, true);
        $_SERVER['HOME'] = $fakeHome;
        putenv("HOME={$fakeHome}");
        
        $testPath = $fakeHome . '/.memex/knowledge-base';
        mkdir($testPath, 0755, true);
        
        try {
            $resolved = ApplicationHelper::resolveKnowledgeBasePath(null);
            
            $this->assertIsString($resolved);
            $this->assertStringContainsString('/.memex/knowledge-base', $resolved);
            $this->assertSame(realpath($testPath), $resolved);
        } finally {
            $this->cleanupDirectory($fakeHome);
            
            if ($originalHome !== false && $originalHome !== '') {
                $_SERVER['HOME'] = $originalHome;
                putenv("HOME={$originalHome}");
            }
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
        
        $this->expectException(KnowledgeBaseNotReadableException::class);
        $this->expectExceptionMessage('not readable');
        
        try {
            ApplicationHelper::resolveKnowledgeBasePath($testPath);
        } catch (KnowledgeBaseNotReadableException $e) {
            $this->assertSame(realpath($testPath), $e->realPath);
            throw $e;
        } finally {
            chmod($testPath, 0755);
            rmdir($testPath);
        }
    }

    public function testResolveKnowledgeBasePathUsesConfigWhenNoCliFlag(): void
    {
        $testKbPath = sys_get_temp_dir() . '/memex-test-kb-' . uniqid();
        mkdir($testKbPath, 0755, true);
        
        $configPath = $this->tempDir . '/memex.json';
        file_put_contents($configPath, json_encode([
            'knowledgeBase' => $testKbPath,
        ]));
        
        $resolved = ApplicationHelper::resolveKnowledgeBasePath(null);
        
        $this->assertSame(realpath($testKbPath), $resolved);
        
        rmdir($testKbPath);
    }

    public function testResolveKnowledgeBasePathPrefersCliFlagOverConfig(): void
    {
        $testKbPath = sys_get_temp_dir() . '/memex-test-kb-' . uniqid();
        mkdir($testKbPath, 0755, true);
        
        $configKbPath = sys_get_temp_dir() . '/memex-test-config-kb-' . uniqid();
        mkdir($configKbPath, 0755, true);
        
        $configPath = $this->tempDir . '/memex.json';
        file_put_contents($configPath, json_encode([
            'knowledgeBase' => $configKbPath,
        ]));
        
        $resolved = ApplicationHelper::resolveKnowledgeBasePath($testKbPath);
        
        $this->assertSame(realpath($testKbPath), $resolved);
        
        rmdir($configKbPath);
        rmdir($testKbPath);
    }
}
