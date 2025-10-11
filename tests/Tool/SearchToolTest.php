<?php

declare(strict_types=1);

namespace Memex\Tests\Tool;

use Memex\Service\GuideService;
use Memex\Service\ContextService;
use Memex\Tool\SearchTool;
use PHPUnit\Framework\TestCase;
use Exception;

final class SearchToolTest extends TestCase
{
    public function testSearchBothTypesWithoutTypeFilter(): void
    {
        $guideResults = [
            ['score' => 0.9, 'type' => 'guide', 'slug' => 'guide-1', 'name' => 'Guide', 'title' => 'Test Guide', 'tags' => [], 'content' => 'Guide content'],
        ];
        $contextResults = [
            ['score' => 0.8, 'type' => 'context', 'slug' => 'context-1', 'name' => 'Context', 'title' => 'Test Context', 'tags' => [], 'content' => 'Context content'],
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
        
        $tool = new SearchTool($guideService, $contextService);
        $result = $tool->search('test query', null, 5);
        
        $this->assertTrue($result['success']);
        $this->assertSame('test query', $result['query']);
        $this->assertSame(2, $result['total_results']);
        $this->assertSame(0.9, $result['results'][0]['score']);
        $this->assertSame('guide', $result['results'][0]['type']);
    }

    public function testSearchGuidesOnly(): void
    {
        $guideResults = [
            ['score' => 0.9, 'type' => 'guide', 'slug' => 'guide-1', 'name' => 'Guide', 'title' => 'Test', 'tags' => [], 'content' => 'Content'],
        ];
        
        $guideService = $this->createMock(GuideService::class);
        $guideService->expects($this->once())
            ->method('search')
            ->willReturn($guideResults);
        
        $contextService = $this->createMock(ContextService::class);
        $contextService->expects($this->never())->method('search');
        
        $tool = new SearchTool($guideService, $contextService);
        $result = $tool->search('test', 'guide', 5);
        
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['results']);
        $this->assertSame('guide', $result['results'][0]['type']);
    }

    public function testSearchContextsOnly(): void
    {
        $contextResults = [
            ['score' => 0.8, 'type' => 'context', 'slug' => 'ctx-1', 'name' => 'Context', 'title' => 'Test', 'tags' => [], 'content' => 'Content'],
        ];
        
        $guideService = $this->createMock(GuideService::class);
        $guideService->expects($this->never())->method('search');
        
        $contextService = $this->createMock(ContextService::class);
        $contextService->expects($this->once())
            ->method('search')
            ->willReturn($contextResults);
        
        $tool = new SearchTool($guideService, $contextService);
        $result = $tool->search('test', 'context', 5);
        
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['results']);
        $this->assertSame('context', $result['results'][0]['type']);
    }

    public function testSearchSortsByScoreDescending(): void
    {
        $guideResults = [
            ['score' => 0.7, 'type' => 'guide', 'slug' => 'g1', 'name' => 'G1', 'title' => 'T1', 'tags' => [], 'content' => 'C1'],
        ];
        $contextResults = [
            ['score' => 0.9, 'type' => 'context', 'slug' => 'c1', 'name' => 'C1', 'title' => 'T2', 'tags' => [], 'content' => 'C2'],
        ];
        
        $guideService = $this->createMock(GuideService::class);
        $guideService->method('search')->willReturn($guideResults);
        
        $contextService = $this->createMock(ContextService::class);
        $contextService->method('search')->willReturn($contextResults);
        
        $tool = new SearchTool($guideService, $contextService);
        $result = $tool->search('test', null, 10);
        
        $this->assertSame(0.9, $result['results'][0]['score']);
        $this->assertSame(0.7, $result['results'][1]['score']);
    }

    public function testSearchRespectsLimit(): void
    {
        $guideResults = [
            ['score' => 0.9, 'type' => 'guide', 'slug' => 'g1', 'name' => 'G1', 'title' => 'T1', 'tags' => [], 'content' => 'C1'],
            ['score' => 0.8, 'type' => 'guide', 'slug' => 'g2', 'name' => 'G2', 'title' => 'T2', 'tags' => [], 'content' => 'C2'],
        ];
        $contextResults = [
            ['score' => 0.7, 'type' => 'context', 'slug' => 'c1', 'name' => 'C1', 'title' => 'T3', 'tags' => [], 'content' => 'C3'],
        ];
        
        $guideService = $this->createMock(GuideService::class);
        $guideService->method('search')->willReturn($guideResults);
        
        $contextService = $this->createMock(ContextService::class);
        $contextService->method('search')->willReturn($contextResults);
        
        $tool = new SearchTool($guideService, $contextService);
        $result = $tool->search('test', null, 2);
        
        $this->assertCount(2, $result['results']);
    }

    public function testSearchTruncatesContentPreview(): void
    {
        $longContent = str_repeat('x', 300);
        $guideResults = [
            ['score' => 0.9, 'type' => 'guide', 'slug' => 'g1', 'name' => 'G1', 'title' => 'T1', 'tags' => [], 'content' => $longContent],
        ];
        
        $guideService = $this->createMock(GuideService::class);
        $guideService->method('search')->willReturn($guideResults);
        
        $contextService = $this->createMock(ContextService::class);
        $contextService->method('search')->willReturn([]);
        
        $tool = new SearchTool($guideService, $contextService);
        $result = $tool->search('test', 'guide', 5);
        
        $this->assertStringEndsWith('...', $result['results'][0]['content_preview']);
        $this->assertLessThanOrEqual(203, strlen($result['results'][0]['content_preview']));
    }

    public function testSearchHandlesException(): void
    {
        $guideService = $this->createMock(GuideService::class);
        $guideService->method('search')
            ->willThrowException(new Exception('Search error'));
        
        $contextService = $this->createMock(ContextService::class);
        
        $tool = new SearchTool($guideService, $contextService);
        $result = $tool->search('test', null, 5);
        
        $this->assertFalse($result['success']);
        $this->assertSame('Search error', $result['error']);
    }
}
