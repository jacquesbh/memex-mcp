<?php

declare(strict_types=1);

namespace Memex\Tests\Tool;

use Memex\Service\ContextService;
use Memex\Tool\GetContextTool;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GetContextToolTest extends TestCase
{
    public function testGetReturnsSuccessWithContextData(): void
    {
        $contextData = [
            'name' => 'Test Context',
            'metadata' => ['title' => 'Test', 'tags' => ['php']],
            'content' => 'Test content',
            'sections' => [['title' => 'Section 1']]
        ];
        
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('get')
            ->with('test query')
            ->willReturn($contextData);
        
        $tool = new GetContextTool($service);
        $result = $tool->get('test query');
        
        $this->assertTrue($result['success']);
        $this->assertSame('Test Context', $result['name']);
        $this->assertSame($contextData['metadata'], $result['metadata']);
        $this->assertSame('Test content', $result['content']);
        $this->assertCount(1, $result['sections']);
    }

    public function testGetHandlesException(): void
    {
        $service = $this->createMock(ContextService::class);
        $service->expects($this->once())
            ->method('get')
            ->willThrowException(new RuntimeException('Context not found'));
        
        $tool = new GetContextTool($service);
        $result = $tool->get('missing');
        
        $this->assertFalse($result['success']);
        $this->assertSame('Context not found', $result['error']);
    }

    public function testGetHandlesMissingSections(): void
    {
        $contextData = [
            'name' => 'Test',
            'metadata' => [],
            'content' => 'Content'
        ];
        
        $service = $this->createMock(ContextService::class);
        $service->method('get')->willReturn($contextData);
        
        $tool = new GetContextTool($service);
        $result = $tool->get('test');
        
        $this->assertTrue($result['success']);
        $this->assertSame([], $result['sections']);
    }
}
