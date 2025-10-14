<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\GuideService;

final readonly class DeleteGuideToolExecutor
{
    public function __construct(
        private GuideService $guideService
    ) {}

    public function execute(string $slug): array
    {
        $result = $this->guideService->delete($slug);
        
        return [
            'success' => true,
            'title' => $result['title'],
            'slug' => $result['slug'],
            'type' => $result['type'],
        ];
    }
}
