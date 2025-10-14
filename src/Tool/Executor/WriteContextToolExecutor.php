<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\ContextService;

final readonly class WriteContextToolExecutor
{
    public function __construct(
        private ContextService $contextService
    ) {}

    public function execute(string $uuid, string $name, string $content, array $tags = [], bool $overwrite = false): array
    {
        $result = $this->contextService->write($uuid, $name, $content, $tags, $overwrite);
        
        return [
            'success' => true,
            'action' => $overwrite ? 'updated' : 'created',
            'uuid' => $result['uuid'],
            'slug' => $result['slug'],
            'name' => $result['title'],
            'file' => "contexts/{$result['slug']}.md",
            'tags' => $tags,
            'message' => "Context created/updated. Use UUID '{$result['uuid']}' to retrieve it.",
        ];
    }
}
