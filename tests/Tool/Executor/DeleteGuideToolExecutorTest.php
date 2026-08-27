<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Service\GuideService;
use Memex\Tool\Executor\DeleteGuideToolExecutor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeleteGuideToolExecutorTest extends TestCase
{
    public function testExecuteParameterIsUuid(): void
    {
        $parameter = (new \ReflectionMethod(DeleteGuideToolExecutor::class, 'execute'))->getParameters()[0];

        $this->assertSame('uuid', $parameter->getName());
    }

    public function testExecuteDeletesGuide(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $deleteResult = [
            'success' => true,
            'uuid' => $uuid,
            'slug' => 'test-guide',
            'title' => 'Test Guide',
            'type' => 'guide',
        ];
        
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('deleteByUuid')
            ->with($uuid)
            ->willReturn($deleteResult);
        
        $executor = new DeleteGuideToolExecutor($service);
        
        $result = $executor->execute($uuid);
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame($uuid, $result['uuid']);
        $this->assertSame('test-guide', $result['slug']);
        $this->assertSame('guide', $result['type']);
    }

    public function testExecuteReturnsStructuredError(): void
    {
        $service = $this->createMock(GuideService::class);
        $service->expects($this->once())
            ->method('deleteByUuid')
            ->willThrowException(new RuntimeException('Delete failed'));

        $executor = new DeleteGuideToolExecutor($service);

        $result = $executor->execute('550e8400-e29b-41d4-a716-446655440999');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame(RuntimeException::class, $result['error']['type']);
        $this->assertSame('Delete failed', $result['error']['message']);
        $this->assertSame('delete_guide', $result['error']['context']['tool']);
        $this->assertSame('runtime', $result['error']['details']['category']);
    }
}
