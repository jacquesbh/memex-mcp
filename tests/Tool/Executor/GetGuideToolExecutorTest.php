<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\GuideService;
use Memex\Tool\Executor\GetGuideToolExecutor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GetGuideToolExecutorTest extends TestCase
{
    public function testExecuteReturnsGuide(): void
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
        
        $result = $executor->execute('00000000-0000-4000-8000-000000000001');
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('00000000-0000-4000-8000-000000000001', $result['uuid']);
        $this->assertSame('Test Guide', $result['name']);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('sections', $result);
    }

    public function testExecuteReturnsStructuredError(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('get')
            ->willThrowException(new RuntimeException('Guide lookup failed'));

        $executor = new GetGuideToolExecutor($service);

        $result = $executor->execute('00000000-0000-4000-8000-000000000001');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame(RuntimeException::class, $result['error']['type']);
        $this->assertSame('Guide lookup failed', $result['error']['message']);
        $this->assertSame('get_guide', $result['error']['context']['tool']);
        $this->assertSame('runtime', $result['error']['details']['category']);
    }
}
