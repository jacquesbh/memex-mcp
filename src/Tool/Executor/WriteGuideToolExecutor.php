<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\GuideService;
use Symfony\AI\McpSdk\Capability\Tool\IdentifierInterface;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;
use Symfony\AI\McpSdk\Capability\Tool\ToolCallResult;
use Symfony\AI\McpSdk\Capability\Tool\ToolExecutorInterface;

class WriteGuideToolExecutor implements ToolExecutorInterface, IdentifierInterface
{
    public function __construct(
        private readonly GuideService $guideService
    ) {}

    public function getName(): string
    {
        return 'write_guide';
    }

    public function call(ToolCall $input): ToolCallResult
    {
        try {
            $uuid = $input->arguments['uuid'];
            $title = $input->arguments['title'];
            $content = $input->arguments['content'];
            $tags = $input->arguments['tags'] ?? [];
            $overwrite = $input->arguments['overwrite'] ?? false;
            
            $result = $this->guideService->write($uuid, $title, $content, $tags, $overwrite);
            
            return new ToolCallResult(
                json_encode([
                    'success' => true,
                    'action' => $overwrite ? 'updated' : 'created',
                    'uuid' => $result['uuid'],
                    'slug' => $result['slug'],
                    'title' => $result['title'],
                    'file' => "guides/{$result['slug']}.md",
                    'tags' => $tags,
                    'message' => "Guide created/updated. Use UUID '{$result['uuid']}' to retrieve it.",
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
