<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\GuideService;
use Memex\Tool\Executor\ListGuidesToolExecutor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ListGuidesToolExecutorTest extends TestCase
{
    public function testExecuteReturnsListOfGuides(): void
    {
        $guides = [
            ['slug' => 'guide-1', 'title' => 'Guide 1', 'tags' => ['php']],
            ['slug' => 'guide-2', 'title' => 'Guide 2', 'tags' => ['symfony']],
        ];
        
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('list')
            ->willReturn($guides);
        
        $executor = new ListGuidesToolExecutor($service);
        
        $result = $executor->execute();
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['guides']);
    }

    public function testExecuteHandlesEmptyList(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->method('list')->willReturn([]);
        
        $executor = new ListGuidesToolExecutor($service);
        
        $result = $executor->execute();
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['guides']);
    }

    public function testExecuteReturnsStructuredError(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('list')
            ->willThrowException(new RuntimeException('List guides failed'));

        $executor = new ListGuidesToolExecutor($service);

        $result = $executor->execute();

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame(RuntimeException::class, $result['error']['type']);
        $this->assertSame('List guides failed', $result['error']['message']);
        $this->assertSame('list_guides', $result['error']['context']['tool']);
        $this->assertSame('runtime', $result['error']['details']['category']);
    }
}
