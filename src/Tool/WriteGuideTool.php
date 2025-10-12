<?php

declare(strict_types=1);

namespace Memex\Tool;

use Memex\Service\GuideService;
use PhpMcp\Server\Attributes\McpTool;

class WriteGuideTool
{
    public function __construct(
        private readonly GuideService $guideService
    ) {}

    #[McpTool(
        name: 'write_guide',
        description: 'Write a new guide to the knowledge base or update an existing one'
    )]
    public function write(
        string $uuid,
        string $title,
        string $content,
        array $tags = [],
        bool $overwrite = false
    ): array {
        try {
            $result = $this->guideService->write($uuid, $title, $content, $tags, $overwrite);
            
            return [
                'success' => true,
                'action' => $overwrite ? 'updated' : 'created',
                'uuid' => $result['uuid'],
                'title' => $title,
                'slug' => $result['slug'],
                'file' => "knowledge-base/guides/{$result['slug']}.md",
                'tags' => $tags,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
