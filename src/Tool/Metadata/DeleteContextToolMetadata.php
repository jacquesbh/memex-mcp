<?php

declare(strict_types=1);

namespace Memex\Tool\Metadata;

use Memex\Tool\Metadata\AbstractToolMetadata;

class DeleteContextToolMetadata extends AbstractToolMetadata
{
    public function getName(): string
    {
        return 'delete_context';
    }

    public function getDescription(): ?string
    {
        return 'Delete a context from the knowledge base';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slug' => [
                    'type' => 'string',
                    'description' => 'Slug of the context to delete',
                ],
            ],
            'required' => ['slug'],
        ];
    }
}
