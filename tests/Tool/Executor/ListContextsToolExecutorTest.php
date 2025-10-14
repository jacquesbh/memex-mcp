<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\ContextService;
use Memex\Tool\Executor\ListContextsToolExecutor;
use PHPUnit\Framework\TestCase;

final class ListContextsToolExecutorTest extends TestCase
{
    public function testExecuteReturnsListOfContexts(): void
    {
        $contexts = [
            ['slug' => 'context-1', 'title' => 'Context 1', 'tags' => ['php']],
            ['slug' => 'context-2', 'title' => 'Context 2', 'tags' => ['symfony']],
        ];
        
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('list')
            ->willReturn($contexts);
        
        $executor = new ListContextsToolExecutor($service);
        
        $result = $executor->execute();
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['contexts']);
    }

    public function testExecuteHandlesEmptyList(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->method('list')->willReturn([]);
        
        $executor = new ListContextsToolExecutor($service);
        
        $result = $executor->execute();
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['contexts']);
    }
}
