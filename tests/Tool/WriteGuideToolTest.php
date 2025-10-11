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
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('write')
            ->with('Test Guide', 'Test content', ['php', 'testing'], false)
            ->willReturn('test-guide');
        
        $tool = new WriteGuideTool($service);
        $result = $tool->write('Test Guide', 'Test content', ['php', 'testing'], false);
        
        $this->assertTrue($result['success']);
        $this->assertSame('created', $result['action']);
        $this->assertSame('Test Guide', $result['title']);
        $this->assertSame('test-guide', $result['slug']);
        $this->assertSame('knowledge-base/guides/test-guide.md', $result['file']);
        $this->assertSame(['php', 'testing'], $result['tags']);
    }

    public function testWriteUpdatesExistingGuide(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('write')
            ->with('Existing Guide', 'Updated content', ['updated'], true)
            ->willReturn('existing-guide');
        
        $tool = new WriteGuideTool($service);
        $result = $tool->write('Existing Guide', 'Updated content', ['updated'], true);
        
        $this->assertTrue($result['success']);
        $this->assertSame('updated', $result['action']);
        $this->assertSame('Existing Guide', $result['title']);
        $this->assertSame('existing-guide', $result['slug']);
    }

    public function testWriteWithEmptyTags(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('write')
            ->with('No Tags', 'Content', [], false)
            ->willReturn('no-tags');
        
        $tool = new WriteGuideTool($service);
        $result = $tool->write('No Tags', 'Content');
        
        $this->assertTrue($result['success']);
        $this->assertSame('created', $result['action']);
        $this->assertSame([], $result['tags']);
    }

    public function testWriteHandlesException(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('write')
            ->willThrowException(new RuntimeException('Write failed'));
        
        $tool = new WriteGuideTool($service);
        $result = $tool->write('Failed', 'Content');
        
        $this->assertFalse($result['success']);
        $this->assertSame('Write failed', $result['error']);
    }
}
