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
        $result = $this->contextService->delete($slug);
        
        return [
            'success' => true,
            'title' => $result['title'],
            'slug' => $result['slug'],
            'type' => $result['type'],
        ];
    }
}
