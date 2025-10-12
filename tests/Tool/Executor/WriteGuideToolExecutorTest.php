<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\GuideService;
use Memex\Tool\Executor\WriteGuideToolExecutor;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;

final class WriteGuideToolExecutorTest extends TestCase
{
    public function testGetNameReturnsWriteGuide(): void
    {
        $service = $this->createMock(GuideService::class);
        $executor = new WriteGuideToolExecutor($service);
        
        $this->assertSame('write_guide', $executor->getName());
    }

    public function testCallCreatesGuide(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('write')
            ->with('00000000-0000-4000-8000-000000000001', 'Test Guide', 'Content', ['tag1'], false)
            ->willReturn(['uuid' => '00000000-0000-4000-8000-000000000001', 'slug' => 'test-guide', 'title' => 'Test Guide']);
        
        $executor = new WriteGuideToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'write_guide', [
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'title' => 'Test Guide',
            'content' => 'Content',
            'tags' => ['tag1'],
            'overwrite' => false,
        ]);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertTrue($data['success']);
        $this->assertSame('00000000-0000-4000-8000-000000000001', $data['uuid']);
        $this->assertSame('test-guide', $data['slug']);
        $this->assertSame('created', $data['action']);
    }

    public function testCallUpdatesExistingGuide(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('write')
            ->with('00000000-0000-4000-8000-000000000001', 'Test Guide', 'Content', [], true)
            ->willReturn(['uuid' => '00000000-0000-4000-8000-000000000001', 'slug' => 'test-guide', 'title' => 'Test Guide']);
        
        $executor = new WriteGuideToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'write_guide', [
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'title' => 'Test Guide',
            'content' => 'Content',
            'overwrite' => true,
        ]);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertTrue($data['success']);
        $this->assertSame('updated', $data['action']);
    }

    public function testCallHandlesException(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->method('write')
            ->willThrowException(new \RuntimeException('Error occurred'));
        
        $executor = new WriteGuideToolExecutor($service);
        $toolCall = new ToolCall('test-id', 'write_guide', [
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'title' => 'Test',
            'content' => 'Content',
        ]);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertFalse($data['success']);
        $this->assertSame('Error occurred', $data['error']);
        $this->assertTrue($result->isError);
    }
}
