<?php

declare(strict_types=1);

namespace Memex\Tests\Tool;

use Memex\Service\GuideService;
use Memex\Tool\DeleteGuideTool;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeleteGuideToolTest extends TestCase
{
    public function testDeleteReturnsSuccessWithGuideData(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('delete')
            ->with('test-guide')
            ->willReturn([
                'success' => true,
                'title' => 'Test Guide',
                'slug' => 'test-guide',
                'type' => 'guide'
            ]);
        
        $tool = new DeleteGuideTool($service);
        $result = $tool->delete('test-guide');
        
        $this->assertTrue($result['success']);
        $this->assertSame('Test Guide', $result['title']);
        $this->assertSame('test-guide', $result['slug']);
        $this->assertSame('guide', $result['type']);
    }

    public function testDeleteHandlesException(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('delete')
            ->willThrowException(new RuntimeException('Guide not found'));
        
        $tool = new DeleteGuideTool($service);
        $result = $tool->delete('missing');
        
        $this->assertFalse($result['success']);
        $this->assertSame('Guide not found', $result['error']);
    }
}
