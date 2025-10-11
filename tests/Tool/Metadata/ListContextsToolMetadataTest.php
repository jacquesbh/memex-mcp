<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Metadata;

use Memex\Tool\Metadata\ListContextsToolMetadata;
use PHPUnit\Framework\TestCase;

final class ListContextsToolMetadataTest extends TestCase
{
    private ListContextsToolMetadata $metadata;

    protected function setUp(): void
    {
        $this->metadata = new ListContextsToolMetadata();
    }

    public function testGetNameReturnsListContexts(): void
    {
        $this->assertSame('list_contexts', $this->metadata->getName());
    }

    public function testGetDescriptionReturnsString(): void
    {
        $description = $this->metadata->getDescription();
        
        $this->assertIsString($description);
        $this->assertStringContainsString('context', $description);
    }

    public function testGetInputSchemaReturnsObjectWithNoRequiredProperties(): void
    {
        $schema = $this->metadata->getInputSchema();
        
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertEmpty((array)$schema['properties']);
    }
}
