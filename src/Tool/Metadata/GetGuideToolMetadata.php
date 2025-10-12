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
        return 'Retrieve a technical guide from the knowledge base by UUID';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'uuid' => [
                    'type' => 'string',
                    'description' => 'UUID of the guide (use list_guides or search_knowledge_base to discover UUIDs)',
                ],
            ],
            'required' => ['uuid'],
        ];
    }
}
