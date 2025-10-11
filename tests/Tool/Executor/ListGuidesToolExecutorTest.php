<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\GuideService;
use Memex\Tool\Executor\ListGuidesToolExecutor;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;

final class ListGuidesToolExecutorTest extends TestCase
{
    public function testGetNameReturnsListGuides(): void
    {
        $service = $this->createMock(GuideService::class);
        $executor = new ListGuidesToolExecutor($service);
        
        $this->assertSame('list_guides', $executor->getName());
    }

    public function testCallReturnsListOfGuides(): void
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
        $toolCall = new ToolCall('test-id', 'list_guides', []);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertTrue($data['success']);
        $this->assertSame(2, $data['total']);
        $this->assertCount(2, $data['guides']);
    }

    public function testCallHandlesEmptyList(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->method('list')->willReturn([]);
        
        $executor = new ListGuidesToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'list_guides', []);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertTrue($data['success']);
        $this->assertSame(0, $data['total']);
        $this->assertEmpty($data['guides']);
    }



    public function testCallHandlesRuntimeException(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->method('list')
            ->willThrowException(new \RuntimeException('Database error'));
        
        $executor = new ListGuidesToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'list_guides', []);
        
        $result = $executor->call($toolCall);
        
        $this->assertTrue($result->isError);
        $data = json_decode($result->result, true);
        $this->assertFalse($data['success']);
        $this->assertSame('Database error', $data['error']);
    }
}
