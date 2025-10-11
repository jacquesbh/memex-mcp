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
            $title = $input->arguments['title'];
            $content = $input->arguments['content'];
            $tags = $input->arguments['tags'] ?? [];
            $overwrite = $input->arguments['overwrite'] ?? false;
            
            $slug = $this->guideService->write($title, $content, $tags, $overwrite);
            
            return new ToolCallResult(
                json_encode([
                    'success' => true,
                    'action' => $overwrite ? 'updated' : 'created',
                    'title' => $title,
                    'slug' => $slug,
                    'file' => "guides/{$slug}.md",
                    'tags' => $tags,
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
