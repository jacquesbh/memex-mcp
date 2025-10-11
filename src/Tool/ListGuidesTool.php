<?php

declare(strict_types=1);

namespace Memex\Tool;

use Memex\Service\GuideService;
use PhpMcp\Server\Attributes\McpTool;

class ListGuidesTool
{
    public function __construct(
        private readonly GuideService $guideService
    ) {}

    #[McpTool(
        name: 'list_guides',
        description: 'List all available guides in the knowledge base'
    )]
    public function list(): array
    {
        try {
            $guides = $this->guideService->list();
            
            return [
                'success' => true,
                'total' => count($guides),
                'guides' => $guides,
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
