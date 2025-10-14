<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\ContextService;
use Memex\Service\GuideService;
use Memex\Tool\Executor\SearchToolExecutor;
use PHPUnit\Framework\TestCase;

final class SearchToolExecutorTest extends TestCase
{
    public function testExecuteSearchesBothTypesWhenNoTypeSpecified(): void
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
        
        $result = $executor->execute('test query');
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['total_results']);
        $this->assertSame(0.9, $result['results'][0]['score']);
    }

    public function testExecuteSearchesOnlyGuidesWhenTypeSpecified(): void
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
        
        $result = $executor->execute('test query', 'guide');
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['total_results']);
    }

    public function testExecuteRespectsLimitParameter(): void
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
        
        $executor->execute('test query', null, 10);
    }
}
