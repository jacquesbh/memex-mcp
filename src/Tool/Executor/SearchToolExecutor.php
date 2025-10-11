<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\GuideService;
use Memex\Service\ContextService;
use Symfony\AI\McpSdk\Capability\Tool\IdentifierInterface;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;
use Symfony\AI\McpSdk\Capability\Tool\ToolCallResult;
use Symfony\AI\McpSdk\Capability\Tool\ToolExecutorInterface;

class SearchToolExecutor implements ToolExecutorInterface, IdentifierInterface
{
    public function __construct(
        private readonly GuideService $guideService,
        private readonly ContextService $contextService
    ) {}

    public function getName(): string
    {
        return 'search_knowledge_base';
    }

    public function call(ToolCall $input): ToolCallResult
    {
        try {
            $query = $input->arguments['query'];
            $type = $input->arguments['type'] ?? null;
            $limit = $input->arguments['limit'] ?? 5;
            
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
            
            return new ToolCallResult(
                json_encode([
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
                ], JSON_THROW_ON_ERROR)
            );
        } catch (\Exception $e) {
            return new ToolCallResult(
                json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], JSON_THROW_ON_ERROR),
                isError: true
            );
        }
    }
}
