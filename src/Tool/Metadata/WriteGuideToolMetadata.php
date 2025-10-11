<?php

declare(strict_types=1);

namespace Memex\Tool\Metadata;

use Memex\Tool\Metadata\AbstractToolMetadata;

class WriteGuideToolMetadata extends AbstractToolMetadata
{
    public function getName(): string
    {
        return 'write_guide';
    }

    public function getDescription(): ?string
    {
        return 'Write a new guide to the knowledge base or update an existing one';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Title of the guide',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Markdown content of the guide',
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Tags for categorizing the guide',
                    'default' => [],
                ],
                'overwrite' => [
                    'type' => 'boolean',
                    'description' => 'Whether to overwrite an existing guide',
                    'default' => false,
                ],
            ],
            'required' => ['title', 'content'],
        ];
    }
}
