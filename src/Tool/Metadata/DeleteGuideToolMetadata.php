<?php

declare(strict_types=1);

namespace Memex\Tool\Metadata;

use Memex\Tool\Metadata\AbstractToolMetadata;

class DeleteGuideToolMetadata extends AbstractToolMetadata
{
    public function getName(): string
    {
        return 'delete_guide';
    }

    public function getDescription(): ?string
    {
        return 'Delete a guide from the knowledge base';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slug' => [
                    'type' => 'string',
                    'description' => 'Slug of the guide to delete',
                ],
            ],
            'required' => ['slug'],
        ];
    }
}
