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
        return 'Retrieve a context (prompt/persona/conventions) from the knowledge base by UUID';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'uuid' => [
                    'type' => 'string',
                    'description' => 'UUID of the context (use list_contexts or search_knowledge_base to discover UUIDs)',
                ],
            ],
            'required' => ['uuid'],
        ];
    }
}
