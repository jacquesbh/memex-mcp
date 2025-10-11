<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\GuideService;
use Memex\Tool\Executor\DeleteGuideToolExecutor;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;

final class DeleteGuideToolExecutorTest extends TestCase
{
    public function testGetNameReturnsDeleteGuide(): void
    {
        $service = $this->createMock(GuideService::class);
        $executor = new DeleteGuideToolExecutor($service);
        
        $this->assertSame('delete_guide', $executor->getName());
    }

    public function testCallDeletesGuide(): void
    {
        $deleteResult = [
            'success' => true,
            'slug' => 'test-guide',
            'title' => 'Test Guide',
            'type' => 'guide',
        ];
        
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('delete')
            ->with('test-guide')
            ->willReturn($deleteResult);
        
        $executor = new DeleteGuideToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'delete_guide', ['slug' => 'test-guide']);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertTrue($data['success']);
        $this->assertSame('test-guide', $data['slug']);
        $this->assertSame('guide', $data['type']);
    }

    public function testCallHandlesNotFound(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->method('delete')
            ->willThrowException(new \RuntimeException('Guide not found'));
        
        $executor = new DeleteGuideToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'delete_guide', ['slug' => 'nonexistent']);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertFalse($data['success']);
        $this->assertSame('Guide not found', $data['error']);
        $this->assertTrue($result->isError);
    }
}
