<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\GuideService;
use Memex\Tool\Executor\DeleteGuideToolExecutor;
use PHPUnit\Framework\TestCase;

final class DeleteGuideToolExecutorTest extends TestCase
{
    public function testExecuteDeletesGuide(): void
    {
        $deleteResult = [
            'success' => true,
            'slug' => 'test-guide',
            'title' => 'Test Guide',
            'type' => 'guide',
        ];
        
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('delete')
            ->with('test-guide')
            ->willReturn($deleteResult);
        
        $executor = new DeleteGuideToolExecutor($service);
        
        $result = $executor->execute('test-guide');
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('test-guide', $result['slug']);
        $this->assertSame('guide', $result['type']);
    }
}
