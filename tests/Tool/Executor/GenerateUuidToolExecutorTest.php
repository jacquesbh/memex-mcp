<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use Memex\Tool\Executor\GenerateUuidToolExecutor;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;

final class GenerateUuidToolExecutorTest extends TestCase
{
    public function testGetNameReturnsGenerateUuid(): void
    {
        $executor = new GenerateUuidToolExecutor();
        
        $this->assertSame('generate_uuid', $executor->getName());
    }

    public function testCallGeneratesValidUuidV4(): void
    {
        $executor = new GenerateUuidToolExecutor();
        $toolCall = new ToolCall('test-id', 'generate_uuid', []);
        
        $result = $executor->call($toolCall);
        
        $data = json_decode($result->result, true);
        $this->assertArrayHasKey('uuid', $data);
        
        $uuid = $data['uuid'];
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid,
            'UUID should be a valid v4 UUID'
        );
    }

    public function testCallGeneratesUniqueUuids(): void
    {
        $executor = new GenerateUuidToolExecutor();
        $toolCall = new ToolCall('test-id', 'generate_uuid', []);
        
        $result1 = $executor->call($toolCall);
        $result2 = $executor->call($toolCall);
        
        $data1 = json_decode($result1->result, true);
        $data2 = json_decode($result2->result, true);
        
        $this->assertNotSame($data1['uuid'], $data2['uuid'], 'Generated UUIDs should be unique');
    }
}
