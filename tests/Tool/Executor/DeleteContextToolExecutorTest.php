<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\ContextService;
use Memex\Tool\Executor\DeleteContextToolExecutor;
use PHPUnit\Framework\TestCase;

final class DeleteContextToolExecutorTest extends TestCase
{
    public function testExecuteDeletesContext(): void
    {
        $deleteResult = [
            'success' => true,
            'slug' => 'test-context',
            'title' => 'Test Context',
            'type' => 'guide',
        ];
        
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('delete')
            ->with('test-context')
            ->willReturn($deleteResult);
        
        $executor = new DeleteContextToolExecutor($service);
        
        $result = $executor->execute('test-context');
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('test-context', $result['slug']);
        $this->assertSame('guide', $result['type']);
    }
}
