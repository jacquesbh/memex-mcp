<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\ContextService;

final readonly class GetContextToolExecutor
{
    public function __construct(
        private ContextService $contextService
    ) {}

    public function execute(string $uuid): array
    {
        $context = $this->contextService->get($uuid);
        
        return [
            'success' => true,
            'uuid' => $context['metadata']['uuid'] ?? null,
            'name' => $context['name'],
            'metadata' => $context['metadata'],
            'content' => $context['content'],
            'sections' => $context['sections'] ?? [],
        ];
    }
}
