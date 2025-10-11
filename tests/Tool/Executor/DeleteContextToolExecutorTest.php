<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\ContextService;
use Memex\Tool\Executor\DeleteContextToolExecutor;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;

final class DeleteContextToolExecutorTest extends TestCase
{
    public function testGetNameReturnsDeleteContext(): void
    {
        $service = $this->createMock(ContextService::class);
        $executor = new DeleteContextToolExecutor($service);
        
        $this->assertSame('delete_context', $executor->getName());
    }

    public function testCallDeletesContext(): void
    {
        $deleteResult = [
            'success' => true,
            'slug' => 'test-context',
            'title' => 'Test Context',
            'type' => 'context',
        ];
        
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('delete')
            ->with('test-context')
            ->willReturn($deleteResult);
        
        $executor = new DeleteContextToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'delete_context', ['slug' => 'test-context']);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertTrue($data['success']);
        $this->assertSame('test-context', $data['slug']);
        $this->assertSame('context', $data['type']);
    }

    public function testCallHandlesNotFound(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->method('delete')
            ->willThrowException(new \RuntimeException('Context not found'));
        
        $executor = new DeleteContextToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'delete_context', ['slug' => 'nonexistent']);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertFalse($data['success']);
        $this->assertSame('Context not found', $data['error']);
        $this->assertTrue($result->isError);
    }
}
