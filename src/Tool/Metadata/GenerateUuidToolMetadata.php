<?php

declare(strict_types=1);

namespace Memex\Tool\Metadata;

use Memex\Tool\Metadata\AbstractToolMetadata;

class GenerateUuidToolMetadata extends AbstractToolMetadata
{
    public function getName(): string
    {
        return 'generate_uuid';
    }

    public function getDescription(): ?string
    {
        return 'Generate a unique UUID v4 identifier. Call this before creating a new guide or context with write_guide or write_context.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object)[],
        ];
    }
}
