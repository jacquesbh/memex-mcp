<?php

declare(strict_types=1);

namespace Memex\Tests\Helper;

use Memex\Helper\ServerHelper;
use Memex\Service\ContextService;
use Memex\Service\GuideService;
use Memex\Service\PatternCompilerService;
use Memex\Service\VectorService;
use Memex\Tool\MemexToolChain;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpSdk\Server\JsonRpcHandler;

final class ServerHelperTest extends TestCase
{
    private string $testKbPath;

    protected function setUp(): void
    {
        $this->testKbPath = sys_get_temp_dir() . '/memex-test-' . uniqid();
        mkdir($this->testKbPath, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testKbPath)) {
            $this->recursiveRemoveDirectory($this->testKbPath);
        }
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

    public function testBuildContainerRegistersServices(): void
    {
        $container = ServerHelper::buildContainer($this->testKbPath);
        
        $this->assertNotNull($container->get(MemexToolChain::class));
        $this->assertInstanceOf(MemexToolChain::class, $container->get(MemexToolChain::class));
    }

    public function testBuildContainerReturnsCompiledContainer(): void
    {
        $container = ServerHelper::buildContainer($this->testKbPath);
        
        $this->assertTrue($container->isCompiled());
    }

    public function testBuildContainerCanRetrieveToolChain(): void
    {
        $container = ServerHelper::buildContainer($this->testKbPath);
        
        $toolChain = $container->get(MemexToolChain::class);
        
        $this->assertInstanceOf(MemexToolChain::class, $toolChain);
    }

    public function testCreateJsonRpcHandlerReturnsHandler(): void
    {
        $container = ServerHelper::buildContainer($this->testKbPath);
        $memexToolChain = $container->get(MemexToolChain::class);
        
        $handler = ServerHelper::createJsonRpcHandler($memexToolChain->getChain(), '1.0.0');
        
        $this->assertInstanceOf(JsonRpcHandler::class, $handler);
    }

    public function testCreateJsonRpcHandlerAcceptsCustomVersion(): void
    {
        $container = ServerHelper::buildContainer($this->testKbPath);
        $memexToolChain = $container->get(MemexToolChain::class);
        
        $handler = ServerHelper::createJsonRpcHandler($memexToolChain->getChain(), '2.0.0');
        
        $this->assertInstanceOf(JsonRpcHandler::class, $handler);
    }
}
