<?php

declare(strict_types=1);

namespace Memex\Tool\Metadata;

use Symfony\AI\McpSdk\Capability\Tool\MetadataInterface;
use Symfony\AI\McpSdk\Capability\Tool\ToolAnnotationsInterface;

abstract class AbstractToolMetadata implements MetadataInterface
{
    abstract public function getName(): string;
    
    abstract public function getDescription(): ?string;
    
    abstract public function getInputSchema(): array;
    
    public function getOutputSchema(): ?array
    {
        return null;
    }
    
    public function getTitle(): ?string
    {
        return null;
    }
    
    public function getAnnotations(): ?ToolAnnotationsInterface
    {
        return null;
    }
}
