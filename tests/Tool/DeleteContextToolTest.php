<?php

declare(strict_types=1);

namespace Memex\Tests\Tool;

use Memex\Service\ContextService;
use Memex\Tool\DeleteContextTool;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeleteContextToolTest extends TestCase
{
    public function testDeleteReturnsSuccessWithContextData(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('delete')
            ->with('test-context')
            ->willReturn([
                'success' => true,
                'title' => 'Test Context',
                'slug' => 'test-context',
                'type' => 'context'
            ]);
        
        $tool = new DeleteContextTool($service);
        $result = $tool->delete('test-context');
        
        $this->assertTrue($result['success']);
        $this->assertSame('Test Context', $result['title']);
        $this->assertSame('test-context', $result['slug']);
        $this->assertSame('context', $result['type']);
    }

    public function testDeleteHandlesException(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('delete')
            ->willThrowException(new RuntimeException('Context not found'));
        
        $tool = new DeleteContextTool($service);
        $result = $tool->delete('missing');
        
        $this->assertFalse($result['success']);
        $this->assertSame('Context not found', $result['error']);
    }
}
