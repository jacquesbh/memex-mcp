<?php

declare(strict_types=1);

namespace Memex\Tool;

use Memex\Service\GuideService;
use PhpMcp\Server\Attributes\McpTool;

class DeleteGuideTool
{
    public function __construct(
        private readonly GuideService $guideService
    ) {}

    #[McpTool(
        name: 'delete_guide',
        description: 'Delete a guide from the knowledge base'
    )]
    public function delete(string $slug): array
    {
        try {
            $result = $this->guideService->delete($slug);
            
            return [
                'success' => true,
                'title' => $result['title'],
                'slug' => $result['slug'],
                'type' => $result['type'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
