<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\GuideService;

final readonly class WriteGuideToolExecutor
{
    public function __construct(
        private GuideService $guideService
    ) {}

    public function execute(string $uuid, string $title, string $content, array $tags = [], bool $overwrite = false): array
    {
        $result = $this->guideService->write($uuid, $title, $content, $tags, $overwrite);
        
        return [
            'success' => true,
            'action' => $overwrite ? 'updated' : 'created',
            'uuid' => $result['uuid'],
            'slug' => $result['slug'],
            'title' => $result['title'],
            'file' => "guides/{$result['slug']}.md",
            'tags' => $tags,
            'message' => "Guide created/updated. Use UUID '{$result['uuid']}' to retrieve it.",
        ];
    }
}
