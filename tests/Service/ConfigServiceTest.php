<?php

declare(strict_types=1);

namespace Memex\Tests\Service;

use InvalidArgumentException;
use Memex\Service\ConfigService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigServiceTest extends TestCase
{
    private string $tempDir;
    private ?string $originalCwd = null;
    private ?string $originalHome = null;
    private string $fakeHome;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/memex-config-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        
        $this->originalCwd = getcwd();
        if ($this->originalCwd !== false) {
            chdir($this->tempDir);
        }
        
        $this->originalHome = $_SERVER['HOME'] ?? getenv('HOME');
        $this->fakeHome = sys_get_temp_dir() . '/memex-fake-home-' . uniqid();
        mkdir($this->fakeHome, 0755, true);
        $_SERVER['HOME'] = $this->fakeHome;
        putenv("HOME={$this->fakeHome}");
    }

    protected function tearDown(): void
    {
        if ($this->originalCwd !== null && $this->originalCwd !== false) {
            chdir($this->originalCwd);
        }
        
        if ($this->originalHome !== null && $this->originalHome !== false && $this->originalHome !== '') {
            $_SERVER['HOME'] = $this->originalHome;
            putenv("HOME={$this->originalHome}");
        }
        
        $this->cleanupDirectory($this->tempDir);
        $this->cleanupDirectory($this->fakeHome);
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

    public function testGetKnowledgeBasePathReturnsNullWhenNoConfig(): void
    {
        $service = new ConfigService();
        
        $this->assertNull($service->getKnowledgeBasePath());
    }

    public function testGetKnowledgeBasePathReturnsLocalConfig(): void
    {
        $configPath = $this->tempDir . '/memex.json';
        file_put_contents($configPath, json_encode([
            'knowledgeBase' => '/local/kb/path',
        ]));
        
        $service = new ConfigService();
        
        $this->assertSame('/local/kb/path', $service->getKnowledgeBasePath());
    }

    public function testGetKnowledgeBasePathReturnsGlobalConfig(): void
    {
        $globalMemexDir = $this->fakeHome . '/.memex';
        $globalConfigPath = $globalMemexDir . '/memex.json';
        
        mkdir($globalMemexDir, 0755, true);
        
        file_put_contents($globalConfigPath, json_encode([
            'knowledgeBase' => '/global/kb/path',
        ]));
        
        $service = new ConfigService();
        $this->assertSame('/global/kb/path', $service->getKnowledgeBasePath());
    }

    public function testGetKnowledgeBasePathPrefersLocalOverGlobal(): void
    {
        $globalMemexDir = $this->fakeHome . '/.memex';
        $globalConfigPath = $globalMemexDir . '/memex.json';
        
        mkdir($globalMemexDir, 0755, true);
        
        file_put_contents($globalConfigPath, json_encode([
            'knowledgeBase' => '/global/kb/path',
        ]));
        
        $localConfigPath = $this->tempDir . '/memex.json';
        file_put_contents($localConfigPath, json_encode([
            'knowledgeBase' => '/local/kb/path',
        ]));
        
        $service = new ConfigService();
        $this->assertSame('/local/kb/path', $service->getKnowledgeBasePath());
    }

    public function testGetKnowledgeBasePathThrowsOnInvalidJson(): void
    {
        $configPath = $this->tempDir . '/memex.json';
        file_put_contents($configPath, '{invalid json}');
        
        $service = new ConfigService();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON in config file');
        
        $service->getKnowledgeBasePath();
    }

    public function testGetKnowledgeBasePathThrowsOnNonObjectJson(): void
    {
        $configPath = $this->tempDir . '/memex.json';
        file_put_contents($configPath, '["array", "not", "object"]');
        
        $service = new ConfigService();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config file must contain a JSON object');
        
        $service->getKnowledgeBasePath();
    }

    public function testGetKnowledgeBasePathThrowsOnInvalidKnowledgeBaseType(): void
    {
        $configPath = $this->tempDir . '/memex.json';
        file_put_contents($configPath, json_encode([
            'knowledgeBase' => 123,
        ]));
        
        $service = new ConfigService();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'knowledgeBase' must be a string");
        
        $service->getKnowledgeBasePath();
    }



    public function testGetKnowledgeBasePathThrowsOnUnreadableFile(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('File permissions test skipped on Windows');
        }
        
        $configPath = $this->tempDir . '/memex.json';
        file_put_contents($configPath, json_encode([
            'knowledgeBase' => '/path/to/kb',
        ]));
        
        chmod($configPath, 0000);
        
        $service = new ConfigService();
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Config file is not readable');
        
        try {
            $service->getKnowledgeBasePath();
        } finally {
            chmod($configPath, 0644);
        }
    }

    public function testGetKnowledgeBasePathAcceptsConfigWithoutKnowledgeBase(): void
    {
        $configPath = $this->tempDir . '/memex.json';
        file_put_contents($configPath, json_encode([]));
        
        $service = new ConfigService();
        
        $this->assertNull($service->getKnowledgeBasePath());
    }

    public function testGetKnowledgeBasePathAcceptsEmptyConfig(): void
    {
        $configPath = $this->tempDir . '/memex.json';
        file_put_contents($configPath, '{}');
        
        $service = new ConfigService();
        
        $this->assertNull($service->getKnowledgeBasePath());
    }

    public function testGetKnowledgeBasePathHandlesEmptyHomeDirectory(): void
    {
        $originalServer = $_SERVER['HOME'] ?? null;
        $originalEnv = getenv('HOME');
        
        $_SERVER['HOME'] = '';
        putenv('HOME=');
        
        try {
            $service = new ConfigService();
            $path = $service->getKnowledgeBasePath();
            
            $this->assertNull($path);
        } finally {
            if ($originalServer !== null) {
                $_SERVER['HOME'] = $originalServer;
            }
            if ($originalEnv !== false) {
                putenv("HOME={$originalEnv}");
            }
        }
    }

    public function testGetKnowledgeBasePathReturnsNullWhenBothConfigsAbsent(): void
    {
        $service = new ConfigService();
        $this->assertNull($service->getKnowledgeBasePath());
    }
}
