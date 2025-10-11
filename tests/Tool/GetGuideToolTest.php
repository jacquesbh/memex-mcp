<?php

declare(strict_types=1);

namespace Memex\Tests\Tool;

use Memex\Service\GuideService;
use Memex\Tool\GetGuideTool;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GetGuideToolTest extends TestCase
{
    public function testGetReturnsSuccessWithGuideData(): void
    {
        $guideData = [
            'name' => 'Test Guide',
            'metadata' => ['title' => 'Test', 'tags' => ['php']],
            'content' => 'Test content',
            'sections' => [['title' => 'Section 1']]
        ];
        
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('get')
            ->with('test query')
            ->willReturn($guideData);
        
        $tool = new GetGuideTool($service);
        $result = $tool->get('test query');
        
        $this->assertTrue($result['success']);
        $this->assertSame('Test Guide', $result['name']);
        $this->assertSame($guideData['metadata'], $result['metadata']);
        $this->assertSame('Test content', $result['content']);
        $this->assertCount(1, $result['sections']);
    }

    public function testGetHandlesException(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('get')
            ->willThrowException(new RuntimeException('Guide not found'));
        
        $tool = new GetGuideTool($service);
        $result = $tool->get('missing');
        
        $this->assertFalse($result['success']);
        $this->assertSame('Guide not found', $result['error']);
    }

    public function testGetHandlesMissingSections(): void
    {
        $guideData = [
            'name' => 'Test',
            'metadata' => [],
            'content' => 'Content'
        ];
        
        $service = $this->createMock(GuideService::class);
        $service->method('get')->willReturn($guideData);
        
        $tool = new GetGuideTool($service);
        $result = $tool->get('test');
        
        $this->assertTrue($result['success']);
        $this->assertSame([], $result['sections']);
    }
}
