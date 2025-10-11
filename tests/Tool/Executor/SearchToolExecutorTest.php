<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\ContextService;
use Memex\Service\GuideService;
use Memex\Tool\Executor\SearchToolExecutor;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;

final class SearchToolExecutorTest extends TestCase
{
    public function testGetNameReturnsSearchKnowledgeBase(): void
    {
        $guideService = $this->createMock(GuideService::class);
        $contextService = $this->createMock(ContextService::class);
        $executor = new SearchToolExecutor($guideService, $contextService);
        
        $this->assertSame('search_knowledge_base', $executor->getName());
    }

    public function testCallSearchesBothTypesWhenNoTypeSpecified(): void
    {
        $guideResults = [
            ['score' => 0.9, 'type' => 'guide', 'slug' => 'g1', 'name' => 'Guide 1', 'title' => 'G1', 'tags' => [], 'content' => 'guide content'],
        ];
        $contextResults = [
            ['score' => 0.85, 'type' => 'context', 'slug' => 'c1', 'name' => 'Context 1', 'title' => 'C1', 'tags' => [], 'content' => 'context content'],
        ];
        
        $guideService = $this->createMock(GuideService::class);
        $guideService->expects($this->once())
            ->method('search')
            ->with('test query', 5)
            ->willReturn($guideResults);
        
        $contextService = $this->createMock(ContextService::class);
        $contextService->expects($this->once())
            ->method('search')
            ->with('test query', 5)
            ->willReturn($contextResults);
        
        $executor = new SearchToolExecutor($guideService, $contextService);
        $toolCall = new ToolCall('test-id', 'search_knowledge_base', ['query' => 'test query']);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertTrue($data['success']);
        $this->assertSame(2, $data['total_results']);
        $this->assertSame(0.9, $data['results'][0]['score']);
    }

    public function testCallSearchesOnlyGuidesWhenTypeSpecified(): void
    {
        $guideResults = [
            ['score' => 0.9, 'type' => 'guide', 'slug' => 'g1', 'name' => 'Guide 1', 'title' => 'G1', 'tags' => [], 'content' => 'guide content'],
        ];
        
        $guideService = $this->createMock(GuideService::class);
        $guideService->expects($this->once())
            ->method('search')
            ->willReturn($guideResults);
        
        $contextService = $this->createMock(ContextService::class);
        $contextService->expects($this->never())
            ->method('search');
        
        $executor = new SearchToolExecutor($guideService, $contextService);
        $toolCall = new ToolCall('test-id', 'search_knowledge_base', [
            'query' => 'test query',
            'type' => 'guide',
        ]);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertTrue($data['success']);
        $this->assertSame(1, $data['total_results']);
    }

    public function testCallRespectsLimitParameter(): void
    {
        $guideService = $this->createMock(GuideService::class);
        $guideService->expects($this->once())
            ->method('search')
            ->with('test query', 10)
            ->willReturn([]);
        
        $contextService = $this->createMock(ContextService::class);
        $contextService->expects($this->once())
            ->method('search')
            ->with('test query', 10)
            ->willReturn([]);
        
        $executor = new SearchToolExecutor($guideService, $contextService);
        $toolCall = new ToolCall('test-id', 'search_knowledge_base', [
            'query' => 'test query',
            'limit' => 10,
        ]);
        
        $executor->call($toolCall);
    }

    public function testCallHandlesException(): void
    {
        $guideService = $this->createMock(GuideService::class);
        $guideService->method('search')
            ->willThrowException(new \RuntimeException('Search failed'));
        
        $contextService = $this->createMock(ContextService::class);
        
        $executor = new SearchToolExecutor($guideService, $contextService);
        $toolCall = new ToolCall('test-id', 'search_knowledge_base', ['query' => 'test']);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertFalse($data['success']);
        $this->assertSame('Search failed', $data['error']);
        $this->assertTrue($result->isError);
    }
}
