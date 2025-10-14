<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Tool\Executor\GenerateUuidToolExecutor;
use PHPUnit\Framework\TestCase;

final class GenerateUuidToolExecutorTest extends TestCase
{
    public function testExecuteGeneratesValidUuidV4(): void
    {
        $executor = new GenerateUuidToolExecutor();
        
        $result = $executor->execute();
        
        $this->assertIsArray($result);
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
        
        $this->assertNotSame($result1['uuid'], $result2['uuid'], 'Generated UUIDs should be unique');
    }
}
