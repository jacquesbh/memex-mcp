<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\GuideService;

final readonly class DeleteGuideToolExecutor
{
    public function __construct(
        private GuideService $guideService
    ) {}

    public function execute(string $uuid): array
    {
        try {
            $result = $this->guideService->deleteByUuid($uuid);

            return [
                'success' => true,
                'uuid' => $result['uuid'],
                'title' => $result['title'],
                'slug' => $result['slug'],
                'type' => $result['type'],
            ];
        } catch (\Throwable $error) {
            return ToolErrorResponse::fromThrowable($error, ['tool' => 'delete_guide']);
        }
    }
}
