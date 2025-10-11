<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\GuideService;
use Symfony\AI\McpSdk\Capability\Tool\IdentifierInterface;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;
use Symfony\AI\McpSdk\Capability\Tool\ToolCallResult;
use Symfony\AI\McpSdk\Capability\Tool\ToolExecutorInterface;

class DeleteGuideToolExecutor implements ToolExecutorInterface, IdentifierInterface
{
    public function __construct(
        private readonly GuideService $guideService
    ) {}

    public function getName(): string
    {
        return 'delete_guide';
    }

    public function call(ToolCall $input): ToolCallResult
    {
        try {
            $result = $this->guideService->delete($input->arguments['slug']);
            
            return new ToolCallResult(
                json_encode([
                    'success' => true,
                    'title' => $result['title'],
                    'slug' => $result['slug'],
                    'type' => $result['type'],
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
