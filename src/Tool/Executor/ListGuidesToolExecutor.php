<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\GuideService;
use Symfony\AI\McpSdk\Capability\Tool\IdentifierInterface;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;
use Symfony\AI\McpSdk\Capability\Tool\ToolCallResult;
use Symfony\AI\McpSdk\Capability\Tool\ToolExecutorInterface;

class ListGuidesToolExecutor implements ToolExecutorInterface, IdentifierInterface
{
    public function __construct(
        private readonly GuideService $guideService
    ) {}

    public function getName(): string
    {
        return 'list_guides';
    }

    public function call(ToolCall $input): ToolCallResult
    {
        try {
            $guides = $this->guideService->list();
            
            return new ToolCallResult(
                json_encode([
                    'success' => true,
                    'total' => count($guides),
                    'guides' => $guides,
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
