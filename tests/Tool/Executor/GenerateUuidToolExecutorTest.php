<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Tool\Executor\GenerateUuidToolExecutor;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class GenerateUuidToolExecutorTest extends TestCase
{
    public function testExecuteGeneratesValidUuidV4(): void
    {
        $executor = new GenerateUuidToolExecutor();
        
        $result = $executor->execute();
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('uuid', $result);
        
        $uuid = $result['uuid'];
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid,
            'UUID should be a valid v4 UUID'
        );
    }

    public function testExecuteGeneratesUniqueUuids(): void
    {
        $executor = new GenerateUuidToolExecutor();
        
        $result1 = $executor->execute();
        $result2 = $executor->execute();
        
        $this->assertTrue($result1['success']);
        $this->assertTrue($result2['success']);
        $this->assertNotSame($result1['uuid'], $result2['uuid'], 'Generated UUIDs should be unique');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testExecuteReturnsErrorResponseWhenUuidGenerationFails(): void
    {
        if (class_exists('Symfony\\Component\\Uid\\Uuid', false)) {
            $this->markTestSkipped('Symfony Uuid already loaded.');
        }

        eval('namespace Symfony\\Component\\Uid; final class Uuid { public static function v4(): self { throw new \\RuntimeException("boom"); } }');

        $executor = new GenerateUuidToolExecutor();

        $result = $executor->execute();

        $this->assertFalse($result['success']);
        $this->assertSame(\RuntimeException::class, $result['error']['type']);
        $this->assertSame('boom', $result['error']['message']);
        $this->assertSame(['tool' => 'generate_uuid'], $result['error']['context']);
        $this->assertSame('runtime', $result['error']['details']['category']);
        $this->assertSame(0, $result['error']['details']['code']);
    }
}
