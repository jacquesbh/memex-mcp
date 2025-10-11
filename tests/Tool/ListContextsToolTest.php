<?php

declare(strict_types=1);

namespace Memex\Tests\Tool;

use Memex\Service\ContextService;
use Memex\Tool\ListContextsTool;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ListContextsToolTest extends TestCase
{
    public function testListReturnsSuccessWithContexts(): void
    {
        $contexts = [
            ['slug' => 'context-1', 'name' => 'Context 1'],
            ['slug' => 'context-2', 'name' => 'Context 2'],
        ];
        
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('list')
            ->willReturn($contexts);
        
        $tool = new ListContextsTool($service);
        $result = $tool->list();
        
        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['contexts']);
        $this->assertSame('context-1', $result['contexts'][0]['slug']);
    }

    public function testListHandlesException(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('list')
            ->willThrowException(new RuntimeException('Database error'));
        
        $tool = new ListContextsTool($service);
        $result = $tool->list();
        
        $this->assertFalse($result['success']);
        $this->assertSame('Database error', $result['error']);
    }

    public function testListHandlesEmptyList(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->method('list')->willReturn([]);
        
        $tool = new ListContextsTool($service);
        $result = $tool->list();
        
        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['contexts']);
    }
}
