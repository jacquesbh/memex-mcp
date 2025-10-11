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
        string $title,
        string $content,
        array $tags = [],
        bool $overwrite = false
    ): array {
        try {
            $slug = $this->guideService->write($title, $content, $tags, $overwrite);
            
            return [
                'success' => true,
                'action' => $overwrite ? 'updated' : 'created',
                'title' => $title,
                'slug' => $slug,
                'file' => "knowledge-base/guides/{$slug}.md",
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
