<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\ContextService;
use Memex\Tool\Executor\ListContextsToolExecutor;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;

final class ListContextsToolExecutorTest extends TestCase
{
    public function testGetNameReturnsListContexts(): void
    {
        $service = $this->createMock(ContextService::class);
        $executor = new ListContextsToolExecutor($service);
        
        $this->assertSame('list_contexts', $executor->getName());
    }

    public function testCallReturnsListOfContexts(): void
    {
        $contexts = [
            ['slug' => 'context-1', 'name' => 'Context 1', 'tags' => ['expert']],
            ['slug' => 'context-2', 'name' => 'Context 2', 'tags' => ['sylius']],
        ];
        
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('list')
            ->willReturn($contexts);
        
        $executor = new ListContextsToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'list_contexts', []);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertTrue($data['success']);
        $this->assertSame(2, $data['total']);
        $this->assertCount(2, $data['contexts']);
    }

    public function testCallHandlesEmptyList(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->method('list')->willReturn([]);
        
        $executor = new ListContextsToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'list_contexts', []);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertTrue($data['success']);
        $this->assertSame(0, $data['total']);
        $this->assertEmpty($data['contexts']);
    }



    public function testCallHandlesRuntimeException(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->method('list')
            ->willThrowException(new \RuntimeException('Database error'));
        
        $executor = new ListContextsToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'list_contexts', []);
        
        $result = $executor->call($toolCall);
        
        $this->assertTrue($result->isError);
        $data = json_decode($result->result, true);
        $this->assertFalse($data['success']);
        $this->assertSame('Database error', $data['error']);
    }
}
