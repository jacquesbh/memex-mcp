<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\ContextService;
use Memex\Tool\Executor\GetContextToolExecutor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GetContextToolExecutorTest extends TestCase
{
    public function testExecuteReturnsContext(): void
    {
        $contextData = [
            'name' => 'Test Context',
            'metadata' => ['uuid' => '00000000-0000-4000-8000-000000000001', 'title' => 'Test Context', 'tags' => ['ai']],
            'content' => 'Context content',
            'sections' => [['title' => 'Section 1', 'content' => 'Content 1']],
        ];
        
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('get')
            ->with('00000000-0000-4000-8000-000000000001')
            ->willReturn($contextData);
        
        $executor = new GetContextToolExecutor($service);
        
        $result = $executor->execute('00000000-0000-4000-8000-000000000001');
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('00000000-0000-4000-8000-000000000001', $result['uuid']);
        $this->assertSame('Test Context', $result['name']);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('sections', $result);
    }

    public function testExecuteReturnsStructuredError(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('get')
            ->willThrowException(new RuntimeException('Context lookup failed'));

        $executor = new GetContextToolExecutor($service);

        $result = $executor->execute('00000000-0000-4000-8000-000000000001');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame(RuntimeException::class, $result['error']['type']);
        $this->assertSame('Context lookup failed', $result['error']['message']);
        $this->assertSame('get_context', $result['error']['context']['tool']);
        $this->assertSame('runtime', $result['error']['details']['category']);
    }
}
