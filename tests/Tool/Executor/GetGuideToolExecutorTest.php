<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\GuideService;
use Memex\Tool\Executor\GetGuideToolExecutor;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;

final class GetGuideToolExecutorTest extends TestCase
{
    public function testGetNameReturnsGetGuide(): void
    {
        $service = $this->createMock(GuideService::class);
        $executor = new GetGuideToolExecutor($service);
        
        $this->assertSame('get_guide', $executor->getName());
    }

    public function testCallReturnsGuide(): void
    {
        $guideData = [
            'name' => 'Test Guide',
            'metadata' => ['uuid' => '00000000-0000-4000-8000-000000000001', 'title' => 'Test Guide', 'tags' => ['php']],
            'content' => 'Guide content',
            'sections' => [['title' => 'Section 1', 'content' => 'Content 1']],
        ];
        
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('get')
            ->with('00000000-0000-4000-8000-000000000001')
            ->willReturn($guideData);
        
        $executor = new GetGuideToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'get_guide', ['uuid' => '00000000-0000-4000-8000-000000000001']);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertTrue($data['success']);
        $this->assertSame('00000000-0000-4000-8000-000000000001', $data['uuid']);
        $this->assertSame('Test Guide', $data['name']);
        $this->assertArrayHasKey('metadata', $data);
        $this->assertArrayHasKey('sections', $data);
    }

    public function testCallHandlesNotFound(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->method('get')
            ->willThrowException(new \RuntimeException('Guide not found'));
        
        $executor = new GetGuideToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'get_guide', ['uuid' => '00000000-0000-4000-8000-000000000001']);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertFalse($data['success']);
        $this->assertSame('Guide not found', $data['error']);
        $this->assertTrue($result->isError);
    }
}
