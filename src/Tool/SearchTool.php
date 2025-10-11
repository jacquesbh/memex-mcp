<?php

declare(strict_types=1);

namespace Memex\Tool;

use Memex\Service\GuideService;
use Memex\Service\ContextService;
use PhpMcp\Server\Attributes\McpTool;

class SearchTool
{
    public function __construct(
        private readonly GuideService $guideService,
        private readonly ContextService $contextService
    ) {}

    #[McpTool(
        name: 'search_knowledge_base',
        description: 'Search the knowledge base using semantic search. Searches both guides and contexts.'
    )]
    public function search(
        string $query,
        ?string $type = null,
        int $limit = 5
    ): array {
        try {
            $results = [];
            
            if ($type === null || $type === 'guide') {
                $guideResults = $this->guideService->search($query, $limit);
                $results = array_merge($results, $guideResults);
            }
            
            if ($type === null || $type === 'context') {
                $contextResults = $this->contextService->search($query, $limit);
                $results = array_merge($results, $contextResults);
            }
            
            usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
            $results = array_slice($results, 0, $limit);
            
            return [
                'success' => true,
                'query' => $query,
                'total_results' => count($results),
                'results' => array_map(function($result) {
                    return [
                        'score' => $result['score'],
                        'type' => $result['type'],
                        'slug' => $result['slug'],
                        'name' => $result['name'],
                        'title' => $result['title'],
                        'tags' => $result['tags'],
                        'content_preview' => substr($result['content'], 0, 200) . '...',
                    ];
                }, $results),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
