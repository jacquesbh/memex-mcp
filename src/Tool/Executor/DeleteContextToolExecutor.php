<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\ContextService;

final readonly class DeleteContextToolExecutor
{
    public function __construct(
        private ContextService $contextService
    ) {}

    public function execute(string $slug): array
    {
        try {
            $result = $this->contextService->delete($slug);

            return [
                'success' => true,
                'title' => $result['title'],
                'slug' => $result['slug'],
                'type' => $result['type'],
            ];
        } catch (\Throwable $error) {
            return ToolErrorResponse::fromThrowable($error, ['tool' => 'delete_context']);
        }
    }
}
