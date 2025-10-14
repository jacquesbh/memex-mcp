<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\GuideService;
use Memex\Service\ContextService;

final readonly class SearchToolExecutor
{
    public function __construct(
        private GuideService $guideService,
        private ContextService $contextService
    ) {}

    public function execute(string $query, ?string $type = null, int $limit = 5): array
    {
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
    }
}
