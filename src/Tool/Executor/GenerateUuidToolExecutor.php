<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Symfony\AI\McpSdk\Capability\Tool\IdentifierInterface;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;
use Symfony\AI\McpSdk\Capability\Tool\ToolCallResult;
use Symfony\AI\McpSdk\Capability\Tool\ToolExecutorInterface;
use Symfony\Component\Uid\Uuid;

class GenerateUuidToolExecutor implements ToolExecutorInterface, IdentifierInterface
{
    public function getName(): string
    {
        return 'generate_uuid';
    }

    public function call(ToolCall $input): ToolCallResult
    {
        $uuid = Uuid::v4()->toString();
        
        return new ToolCallResult(
            json_encode([
                'uuid' => $uuid,
            ], JSON_THROW_ON_ERROR)
        );
    }
}
