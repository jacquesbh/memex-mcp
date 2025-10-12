<?php

declare(strict_types=1);

namespace Memex\Tests\Tool;

use Memex\Service\GuideService;
use Memex\Tool\WriteGuideTool;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WriteGuideToolTest extends TestCase
{
    public function testWriteCreatesNewGuide(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('write')
            ->with($uuid, 'Test Guide', 'Test content', ['php', 'testing'], false)
            ->willReturn([
                'uuid' => $uuid,
                'slug' => 'test-guide',
                'title' => 'Test Guide',
            ]);
        
        $tool = new WriteGuideTool($service);
        $result = $tool->write($uuid, 'Test Guide', 'Test content', ['php', 'testing'], false);
        
        $this->assertTrue($result['success']);
        $this->assertSame('created', $result['action']);
        $this->assertSame('Test Guide', $result['title']);
        $this->assertSame('test-guide', $result['slug']);
        $this->assertSame('knowledge-base/guides/test-guide.md', $result['file']);
        $this->assertSame(['php', 'testing'], $result['tags']);
    }

    public function testWriteUpdatesExistingGuide(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440001';
        
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('write')
            ->with($uuid, 'Existing Guide', 'Updated content', ['updated'], true)
            ->willReturn([
                'uuid' => $uuid,
                'slug' => 'existing-guide',
                'title' => 'Existing Guide',
            ]);
        
        $tool = new WriteGuideTool($service);
        $result = $tool->write($uuid, 'Existing Guide', 'Updated content', ['updated'], true);
        
        $this->assertTrue($result['success']);
        $this->assertSame('updated', $result['action']);
        $this->assertSame('Existing Guide', $result['title']);
        $this->assertSame('existing-guide', $result['slug']);
    }

    public function testWriteWithEmptyTags(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440002';
        
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('write')
            ->with($uuid, 'No Tags', 'Content', [], false)
            ->willReturn([
                'uuid' => $uuid,
                'slug' => 'no-tags',
                'title' => 'No Tags',
            ]);
        
        $tool = new WriteGuideTool($service);
        $result = $tool->write($uuid, 'No Tags', 'Content');
        
        $this->assertTrue($result['success']);
        $this->assertSame('created', $result['action']);
        $this->assertSame([], $result['tags']);
    }

    public function testWriteHandlesException(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440003';
        
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('write')
            ->willThrowException(new RuntimeException('Write failed'));
        
        $tool = new WriteGuideTool($service);
        $result = $tool->write($uuid, 'Failed', 'Content');
        
        $this->assertFalse($result['success']);
        $this->assertSame('Write failed', $result['error']);
    }
}
