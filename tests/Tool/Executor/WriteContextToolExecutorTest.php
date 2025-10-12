<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\ContextService;
use Memex\Tool\Executor\WriteContextToolExecutor;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;

final class WriteContextToolExecutorTest extends TestCase
{
    public function testGetNameReturnsWriteContext(): void
    {
        $service = $this->createMock(ContextService::class);
        $executor = new WriteContextToolExecutor($service);
        
        $this->assertSame('write_context', $executor->getName());
    }

    public function testCallCreatesContext(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('write')
            ->with('00000000-0000-4000-8000-000000000001', 'Test Context', 'Content', ['tag1'], false)
            ->willReturn(['uuid' => '00000000-0000-4000-8000-000000000001', 'slug' => 'test-context', 'title' => 'Test Context']);
        
        $executor = new WriteContextToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'write_context', [
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'name' => 'Test Context',
            'content' => 'Content',
            'tags' => ['tag1'],
            'overwrite' => false,
        ]);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertTrue($data['success']);
        $this->assertSame('00000000-0000-4000-8000-000000000001', $data['uuid']);
        $this->assertSame('test-context', $data['slug']);
        $this->assertSame('created', $data['action']);
    }

    public function testCallHandlesException(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->method('write')
            ->willThrowException(new \RuntimeException('Error occurred'));
        
        $executor = new WriteContextToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'write_context', [
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'name' => 'Test',
            'content' => 'Content',
        ]);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertFalse($data['success']);
        $this->assertSame('Error occurred', $data['error']);
        $this->assertTrue($result->isError);
    }
}
