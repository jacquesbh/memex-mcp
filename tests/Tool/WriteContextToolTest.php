<?php

declare(strict_types=1);

namespace Memex\Tests\Tool;

use Memex\Service\ContextService;
use Memex\Tool\WriteContextTool;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WriteContextToolTest extends TestCase
{
    public function testWriteCreatesNewContext(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('write')
            ->with('Test Context', 'Test content', ['php', 'testing'], false)
            ->willReturn('test-context');
        
        $tool = new WriteContextTool($service);
        $result = $tool->write('Test Context', 'Test content', ['php', 'testing'], false);
        
        $this->assertTrue($result['success']);
        $this->assertSame('created', $result['action']);
        $this->assertSame('Test Context', $result['name']);
        $this->assertSame('test-context', $result['slug']);
        $this->assertSame('knowledge-base/contexts/test-context.md', $result['file']);
        $this->assertSame(['php', 'testing'], $result['tags']);
    }

    public function testWriteUpdatesExistingContext(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('write')
            ->with('Existing Context', 'Updated content', ['updated'], true)
            ->willReturn('existing-context');
        
        $tool = new WriteContextTool($service);
        $result = $tool->write('Existing Context', 'Updated content', ['updated'], true);
        
        $this->assertTrue($result['success']);
        $this->assertSame('updated', $result['action']);
        $this->assertSame('Existing Context', $result['name']);
        $this->assertSame('existing-context', $result['slug']);
    }

    public function testWriteWithEmptyTags(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('write')
            ->with('No Tags', 'Content', [], false)
            ->willReturn('no-tags');
        
        $tool = new WriteContextTool($service);
        $result = $tool->write('No Tags', 'Content');
        
        $this->assertTrue($result['success']);
        $this->assertSame('created', $result['action']);
        $this->assertSame([], $result['tags']);
    }

    public function testWriteHandlesException(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('write')
            ->willThrowException(new RuntimeException('Write failed'));
        
        $tool = new WriteContextTool($service);
        $result = $tool->write('Failed', 'Content');
        
        $this->assertFalse($result['success']);
        $this->assertSame('Write failed', $result['error']);
    }
}
