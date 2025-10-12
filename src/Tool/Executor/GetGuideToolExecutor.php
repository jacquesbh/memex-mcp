<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\GuideService;
use Symfony\AI\McpSdk\Capability\Tool\IdentifierInterface;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;
use Symfony\AI\McpSdk\Capability\Tool\ToolCallResult;
use Symfony\AI\McpSdk\Capability\Tool\ToolExecutorInterface;

class GetGuideToolExecutor implements ToolExecutorInterface, IdentifierInterface
{
    public function __construct(
        private readonly GuideService $guideService
    ) {}

    public function getName(): string
    {
        return 'get_guide';
    }

    public function call(ToolCall $input): ToolCallResult
    {
        try {
            $guide = $this->guideService->get($input->arguments['uuid']);
            
            return new ToolCallResult(
                json_encode([
                    'success' => true,
                    'uuid' => $guide['metadata']['uuid'] ?? null,
                    'name' => $guide['name'],
                    'metadata' => $guide['metadata'],
                    'content' => $guide['content'],
                    'sections' => $guide['sections'] ?? [],
                ], JSON_THROW_ON_ERROR)
            );
        } catch (\RuntimeException $e) {
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
