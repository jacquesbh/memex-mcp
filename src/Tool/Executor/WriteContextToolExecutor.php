<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\ContextService;
use Symfony\AI\McpSdk\Capability\Tool\IdentifierInterface;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;
use Symfony\AI\McpSdk\Capability\Tool\ToolCallResult;
use Symfony\AI\McpSdk\Capability\Tool\ToolExecutorInterface;

class WriteContextToolExecutor implements ToolExecutorInterface, IdentifierInterface
{
    public function __construct(
        private readonly ContextService $contextService
    ) {}

    public function getName(): string
    {
        return 'write_context';
    }

    public function call(ToolCall $input): ToolCallResult
    {
        try {
            $uuid = $input->arguments['uuid'];
            $name = $input->arguments['name'];
            $content = $input->arguments['content'];
            $tags = $input->arguments['tags'] ?? [];
            $overwrite = $input->arguments['overwrite'] ?? false;
            
            $result = $this->contextService->write($uuid, $name, $content, $tags, $overwrite);
            
            return new ToolCallResult(
                json_encode([
                    'success' => true,
                    'action' => $overwrite ? 'updated' : 'created',
                    'uuid' => $result['uuid'],
                    'slug' => $result['slug'],
                    'name' => $result['title'],
                    'file' => "contexts/{$result['slug']}.md",
                    'tags' => $tags,
                    'message' => "Context created/updated. Use UUID '{$result['uuid']}' to retrieve it.",
                ], JSON_THROW_ON_ERROR)
            );
        } catch (\Exception $e) {
            return new ToolCallResult(
                json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], JSON_THROW_ON_ERROR),
                isError: true
            );
        }
    }
}
