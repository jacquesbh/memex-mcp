<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\ContextService;
use Memex\Tool\Executor\GetContextToolExecutor;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;

final class GetContextToolExecutorTest extends TestCase
{
    public function testGetNameReturnsGetContext(): void
    {
        $service = $this->createMock(ContextService::class);
        $executor = new GetContextToolExecutor($service);
        
        $this->assertSame('get_context', $executor->getName());
    }

    public function testCallReturnsContext(): void
    {
        $contextData = [
            'name' => 'Test Context',
            'metadata' => ['name' => 'Test Context', 'tags' => ['expert']],
            'content' => 'Context content',
            'sections' => [],
        ];
        
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('get')
            ->with('test query')
            ->willReturn($contextData);
        
        $executor = new GetContextToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'get_context', ['query' => 'test query']);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertTrue($data['success']);
        $this->assertSame('Test Context', $data['name']);
        $this->assertArrayHasKey('metadata', $data);
    }

    public function testCallHandlesNotFound(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->method('get')
            ->willThrowException(new \RuntimeException('Context not found'));
        
        $executor = new GetContextToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'get_context', ['query' => 'nonexistent']);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertFalse($data['success']);
        $this->assertSame('Context not found', $data['error']);
        $this->assertTrue($result->isError);
    }
}
