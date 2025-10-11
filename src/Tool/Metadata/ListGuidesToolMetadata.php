<?php

declare(strict_types=1);

namespace Memex\Tool\Metadata;

use Memex\Tool\Metadata\AbstractToolMetadata;

class ListGuidesToolMetadata extends AbstractToolMetadata
{
    public function getName(): string
    {
        return 'list_guides';
    }

    public function getDescription(): ?string
    {
        return 'List all available guides in the knowledge base';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object)[],
            'required' => [],
        ];
    }
}
