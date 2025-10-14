<?php

declare(strict_types=1);

namespace Memex\Tests\Helper;

use Memex\Helper\ServerHelper;
use Memex\Service\ContextService;
use Memex\Service\GuideService;
use Mcp\Server;
use PHPUnit\Framework\TestCase;

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
        
        $this->assertNotNull($container->get(GuideService::class));
        $this->assertInstanceOf(GuideService::class, $container->get(GuideService::class));
        $this->assertNotNull($container->get(ContextService::class));
        $this->assertInstanceOf(ContextService::class, $container->get(ContextService::class));
    }

    public function testBuildContainerReturnsCompiledContainer(): void
    {
        $container = ServerHelper::buildContainer($this->testKbPath);
        
        $this->assertTrue($container->isCompiled());
    }

    public function testCreateServerReturnsServer(): void
    {
        $guideService = $this->createMock(GuideService::class);
        $contextService = $this->createMock(ContextService::class);
        
        $server = ServerHelper::createServer($guideService, $contextService, '1.0.0');
        
        $this->assertInstanceOf(Server::class, $server);
    }

    public function testCreateServerAcceptsCustomVersion(): void
    {
        $guideService = $this->createMock(GuideService::class);
        $contextService = $this->createMock(ContextService::class);
        
        $server = ServerHelper::createServer($guideService, $contextService, '2.0.0');
        
        $this->assertInstanceOf(Server::class, $server);
    }
}
