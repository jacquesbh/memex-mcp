<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\GuideService;
use Memex\Tool\Executor\WriteGuideToolExecutor;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

final class WriteGuideToolExecutorTest extends TestCase
{
    public function testExecuteCreatesGuide(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('write')
            ->with('00000000-0000-4000-8000-000000000001', 'Test Guide', 'Content', ['tag1'], false)
            ->willReturn(['uuid' => '00000000-0000-4000-8000-000000000001', 'slug' => 'test-guide', 'title' => 'Test Guide']);
        
        $executor = new WriteGuideToolExecutor($service);
        
        $result = $executor->execute('00000000-0000-4000-8000-000000000001', 'Test Guide', 'Content', ['tag1'], false);
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('00000000-0000-4000-8000-000000000001', $result['uuid']);
        $this->assertSame('test-guide', $result['slug']);
        $this->assertSame('created', $result['action']);
    }

    public function testExecuteUpdatesExistingGuide(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('write')
            ->with('00000000-0000-4000-8000-000000000001', 'Test Guide', 'Content', [], true)
            ->willReturn(['uuid' => '00000000-0000-4000-8000-000000000001', 'slug' => 'test-guide', 'title' => 'Test Guide']);
        
        $executor = new WriteGuideToolExecutor($service);
        
        $result = $executor->execute('00000000-0000-4000-8000-000000000001', 'Test Guide', 'Content', [], true);
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('updated', $result['action']);
    }

    public function testExecuteReturnsStructuredError(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('write')
            ->willThrowException(new InvalidArgumentException('Invalid guide data'));

        $executor = new WriteGuideToolExecutor($service);

        $result = $executor->execute('00000000-0000-4000-8000-000000000001', 'Bad Guide', 'Content', [], false);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame(InvalidArgumentException::class, $result['error']['type']);
        $this->assertSame('Invalid guide data', $result['error']['message']);
        $this->assertSame('write_guide', $result['error']['context']['tool']);
        $this->assertSame('validation', $result['error']['details']['category']);
    }
}
