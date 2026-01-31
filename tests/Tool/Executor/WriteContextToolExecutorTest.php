<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\ContextService;
use Memex\Tool\Executor\WriteContextToolExecutor;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

final class WriteContextToolExecutorTest extends TestCase
{
    public function testExecuteCreatesGuide(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('write')
            ->with('00000000-0000-4000-8000-000000000001', 'Test Context', 'Content', ['tag1'], false)
            ->willReturn(['uuid' => '00000000-0000-4000-8000-000000000001', 'slug' => 'test-context', 'title' => 'Test Context']);
        
        $executor = new WriteContextToolExecutor($service);
        
        $result = $executor->execute('00000000-0000-4000-8000-000000000001', 'Test Context', 'Content', ['tag1'], false);
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('00000000-0000-4000-8000-000000000001', $result['uuid']);
        $this->assertSame('test-context', $result['slug']);
        $this->assertSame('created', $result['action']);
    }

    public function testExecuteUpdatesExistingGuide(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('write')
            ->with('00000000-0000-4000-8000-000000000001', 'Test Context', 'Content', [], true)
            ->willReturn(['uuid' => '00000000-0000-4000-8000-000000000001', 'slug' => 'test-context', 'title' => 'Test Context']);
        
        $executor = new WriteContextToolExecutor($service);
        
        $result = $executor->execute('00000000-0000-4000-8000-000000000001', 'Test Context', 'Content', [], true);
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('updated', $result['action']);
    }

    public function testExecuteReturnsStructuredError(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('write')
            ->willThrowException(new InvalidArgumentException('Invalid context data'));

        $executor = new WriteContextToolExecutor($service);

        $result = $executor->execute('00000000-0000-4000-8000-000000000001', 'Bad Context', 'Content', [], false);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame(InvalidArgumentException::class, $result['error']['type']);
        $this->assertSame('Invalid context data', $result['error']['message']);
        $this->assertSame('write_context', $result['error']['context']['tool']);
        $this->assertSame('validation', $result['error']['details']['category']);
    }
}
