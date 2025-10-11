<?php

declare(strict_types=1);

namespace Memex\Tool\Metadata;

use Memex\Tool\Metadata\AbstractToolMetadata;

class GetGuideToolMetadata extends AbstractToolMetadata
{
    public function getName(): string
    {
        return 'get_guide';
    }

    public function getDescription(): ?string
    {
        return 'Retrieve a technical guide from the knowledge base using search query';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search query to find the guide',
                ],
            ],
            'required' => ['query'],
        ];
    }
}
