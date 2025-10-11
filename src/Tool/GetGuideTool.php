<?php

declare(strict_types=1);

namespace Memex\Tool;

use Memex\Service\GuideService;
use PhpMcp\Server\Attributes\McpTool;

class GetGuideTool
{
    public function __construct(
        private readonly GuideService $guideService
    ) {}

    #[McpTool(
        name: 'get_guide',
        description: 'Retrieve a technical guide from the knowledge base using search query'
    )]
    public function get(string $query): array
    {
        try {
            $guide = $this->guideService->get($query);
            
            return [
                'success' => true,
                'name' => $guide['name'],
                'metadata' => $guide['metadata'],
                'content' => $guide['content'],
                'sections' => $guide['sections'] ?? [],
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
