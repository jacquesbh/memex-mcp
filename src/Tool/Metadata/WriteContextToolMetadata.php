<?php

declare(strict_types=1);

namespace Memex\Tool\Metadata;

use Memex\Tool\Metadata\AbstractToolMetadata;

class WriteContextToolMetadata extends AbstractToolMetadata
{
    public function getName(): string
    {
        return 'write_context';
    }

    public function getDescription(): ?string
    {
        return 'Write a new context (prompt/persona/conventions) to the knowledge base or update an existing one';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'uuid' => [
                    'type' => 'string',
                    'description' => 'UUID v4 identifier (generate with generate_uuid tool first)',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Name of the context',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Markdown content of the context',
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Tags for categorizing the context',
                    'default' => [],
                ],
                'overwrite' => [
                    'type' => 'boolean',
                    'description' => 'Whether to overwrite an existing context',
                    'default' => false,
                ],
            ],
            'required' => ['uuid', 'name', 'content'],
        ];
    }
}
