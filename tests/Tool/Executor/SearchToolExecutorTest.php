<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\ContextService;
use Memex\Service\GuideService;
use Memex\Tool\Executor\SearchToolExecutor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    public function testExecuteHandlesEmojiContentPreview(): void
    {
        $emojiContent = str_repeat('🎉', 250);
        
        $guideResults = [
            [
                'score' => 0.9,
                'type' => 'guide',
                'slug' => 'emoji-guide',
                'name' => 'Emoji Guide',
                'title' => 'Emoji Guide',
                'tags' => [],
                'content' => $emojiContent
            ],
        ];
        
        $guideService = $this->createMock(GuideService::class);
        $guideService->method('search')->willReturn($guideResults);
        
        $contextService = $this->createMock(ContextService::class);
        $contextService->method('search')->willReturn([]);
        
        $executor = new SearchToolExecutor($guideService, $contextService);
        
        $result = $executor->execute('emoji test', 'guide');
        
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['results']);
        
        $preview = $result['results'][0]['content_preview'];
        $this->assertTrue(mb_check_encoding($preview, 'UTF-8'), 'Preview should be valid UTF-8');
        $this->assertLessThanOrEqual(203, mb_strlen($preview), 'Preview should be max 200 chars + ...');
    }

    public function testExecuteHandlesUnicodeContentPreview(): void
    {
        $unicodeContent = str_repeat('日本語café', 100);
        
        $contextResults = [
            [
                'score' => 0.85,
                'type' => 'context',
                'slug' => 'unicode-context',
                'name' => 'Unicode Context',
                'title' => 'Unicode Context',
                'tags' => [],
                'content' => $unicodeContent
            ],
        ];
        
        $guideService = $this->createMock(GuideService::class);
        $guideService->method('search')->willReturn([]);
        
        $contextService = $this->createMock(ContextService::class);
        $contextService->method('search')->willReturn($contextResults);
        
        $executor = new SearchToolExecutor($guideService, $contextService);
        
        $result = $executor->execute('unicode test', 'context');
        
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['results']);
        
        $preview = $result['results'][0]['content_preview'];
        $this->assertTrue(mb_check_encoding($preview, 'UTF-8'), 'Preview should be valid UTF-8');
        $this->assertStringContainsString('日本語', $preview);
    }

    public function testExecuteHandlesMixedEmojiAndTextPreview(): void
    {
        $mixedContent = 'Welcome 🎉 to the guide! Features: ✨ Fast 🚀 Easy 👍 ' . str_repeat('More content here. ', 20);
        
        $guideResults = [
            [
                'score' => 0.9,
                'type' => 'guide',
                'slug' => 'mixed-guide',
                'name' => 'Mixed Guide',
                'title' => 'Mixed Guide',
                'tags' => ['emoji'],
                'content' => $mixedContent
            ],
        ];
        
        $guideService = $this->createMock(GuideService::class);
        $guideService->method('search')->willReturn($guideResults);
        
        $contextService = $this->createMock(ContextService::class);
        $contextService->method('search')->willReturn([]);
        
        $executor = new SearchToolExecutor($guideService, $contextService);
        
        $result = $executor->execute('features', 'guide');
        
        $this->assertTrue($result['success']);
        $preview = $result['results'][0]['content_preview'];
        
        $this->assertStringContainsString('🎉', $preview);
        $this->assertStringContainsString('✨', $preview);
        $this->assertTrue(mb_check_encoding($preview, 'UTF-8'), 'Preview should be valid UTF-8');
    }

    public function testExecuteReturnsStructuredError(): void
    {
        $guideService = $this->createMock(GuideService::class);
        $guideService->expects($this->once())
            ->method('search')
            ->willThrowException(new RuntimeException('Search failed'));

        $contextService = $this->createMock(ContextService::class);
        $contextService->expects($this->never())
            ->method('search');

        $executor = new SearchToolExecutor($guideService, $contextService);

        $result = $executor->execute('test query');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame(RuntimeException::class, $result['error']['type']);
        $this->assertSame('Search failed', $result['error']['message']);
        $this->assertSame('search_knowledge_base', $result['error']['context']['tool']);
        $this->assertSame('runtime', $result['error']['details']['category']);
    }
}
