<?php

declare(strict_types=1);

namespace Memex\Tool\Metadata;

use Memex\Tool\Metadata\AbstractToolMetadata;

class SearchToolMetadata extends AbstractToolMetadata
{
    public function getName(): string
    {
        return 'search_knowledge_base';
    }

    public function getDescription(): ?string
    {
        return 'Search the knowledge base using semantic search. Searches both guides and contexts.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search query',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['guide', 'context'],
                    'description' => 'Filter by type (optional)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of results',
                    'default' => 5,
                ],
            ],
            'required' => ['query'],
        ];
    }
}
