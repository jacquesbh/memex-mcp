<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\GuideService;

final readonly class GetGuideToolExecutor
{
    public function __construct(
        private GuideService $guideService
    ) {}

    public function execute(string $uuid): array
    {
        try {
            $guide = $this->guideService->get($uuid);

            return [
                'success' => true,
                'uuid' => $guide['metadata']['uuid'] ?? null,
                'name' => $guide['name'],
                'metadata' => $guide['metadata'],
                'content' => $guide['content'],
                'sections' => $guide['sections'] ?? [],
            ];
        } catch (\Throwable $error) {
            return ToolErrorResponse::fromThrowable($error, ['tool' => 'get_guide']);
        }
    }
}
