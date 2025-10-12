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
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('write')
            ->with($uuid, 'Test Context', 'Test content', ['php', 'testing'], false)
            ->willReturn([
                'uuid' => $uuid,
                'slug' => 'test-context',
                'title' => 'Test Context',
            ]);
        
        $tool = new WriteContextTool($service);
        $result = $tool->write($uuid, 'Test Context', 'Test content', ['php', 'testing'], false);
        
        $this->assertTrue($result['success']);
        $this->assertSame('created', $result['action']);
        $this->assertSame('Test Context', $result['name']);
        $this->assertSame('test-context', $result['slug']);
        $this->assertSame('knowledge-base/contexts/test-context.md', $result['file']);
        $this->assertSame(['php', 'testing'], $result['tags']);
    }

    public function testWriteUpdatesExistingContext(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440001';
        
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('write')
            ->with($uuid, 'Existing Context', 'Updated content', ['updated'], true)
            ->willReturn([
                'uuid' => $uuid,
                'slug' => 'existing-context',
                'title' => 'Existing Context',
            ]);
        
        $tool = new WriteContextTool($service);
        $result = $tool->write($uuid, 'Existing Context', 'Updated content', ['updated'], true);
        
        $this->assertTrue($result['success']);
        $this->assertSame('updated', $result['action']);
        $this->assertSame('Existing Context', $result['name']);
        $this->assertSame('existing-context', $result['slug']);
    }

    public function testWriteWithEmptyTags(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440002';
        
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('write')
            ->with($uuid, 'No Tags', 'Content', [], false)
            ->willReturn([
                'uuid' => $uuid,
                'slug' => 'no-tags',
                'title' => 'No Tags',
            ]);
        
        $tool = new WriteContextTool($service);
        $result = $tool->write($uuid, 'No Tags', 'Content');
        
        $this->assertTrue($result['success']);
        $this->assertSame('created', $result['action']);
        $this->assertSame([], $result['tags']);
    }

    public function testWriteHandlesException(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440003';
        
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('write')
            ->willThrowException(new RuntimeException('Write failed'));
        
        $tool = new WriteContextTool($service);
        $result = $tool->write($uuid, 'Failed', 'Content');
        
        $this->assertFalse($result['success']);
        $this->assertSame('Write failed', $result['error']);
    }
}
