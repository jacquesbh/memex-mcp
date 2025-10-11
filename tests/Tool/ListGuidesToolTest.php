<?php

declare(strict_types=1);

namespace Memex\Tests\Tool;

use Memex\Service\GuideService;
use Memex\Tool\ListGuidesTool;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ListGuidesToolTest extends TestCase
{
    public function testListReturnsSuccessWithGuides(): void
    {
        $guides = [
            ['slug' => 'guide-1', 'title' => 'Guide 1'],
            ['slug' => 'guide-2', 'title' => 'Guide 2'],
        ];
        
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('list')
            ->willReturn($guides);
        
        $tool = new ListGuidesTool($service);
        $result = $tool->list();
        
        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['guides']);
        $this->assertSame('guide-1', $result['guides'][0]['slug']);
    }

    public function testListHandlesException(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('list')
            ->willThrowException(new RuntimeException('Database error'));
        
        $tool = new ListGuidesTool($service);
        $result = $tool->list();
        
        $this->assertFalse($result['success']);
        $this->assertSame('Database error', $result['error']);
    }

    public function testListHandlesEmptyList(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->method('list')->willReturn([]);
        
        $tool = new ListGuidesTool($service);
        $result = $tool->list();
        
        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['guides']);
    }
}
