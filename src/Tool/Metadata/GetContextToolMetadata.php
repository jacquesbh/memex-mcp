<?php

declare(strict_types=1);

namespace Memex\Tool\Metadata;

use Memex\Tool\Metadata\AbstractToolMetadata;

class GetContextToolMetadata extends AbstractToolMetadata
{
    public function getName(): string
    {
        return 'get_context';
    }

    public function getDescription(): ?string
    {
        return 'Retrieve a context (prompt/persona/conventions) from the knowledge base using search query';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search query to find the context',
                ],
            ],
            'required' => ['query'],
        ];
    }
}
