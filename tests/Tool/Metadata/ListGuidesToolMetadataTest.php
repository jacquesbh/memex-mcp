<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Metadata;

use Memex\Tool\Metadata\ListGuidesToolMetadata;
use PHPUnit\Framework\TestCase;

final class ListGuidesToolMetadataTest extends TestCase
{
    private ListGuidesToolMetadata $metadata;

    protected function setUp(): void
    {
        $this->metadata = new ListGuidesToolMetadata();
    }

    public function testGetNameReturnsListGuides(): void
    {
        $this->assertSame('list_guides', $this->metadata->getName());
    }

    public function testGetDescriptionReturnsString(): void
    {
        $description = $this->metadata->getDescription();
        
        $this->assertIsString($description);
        $this->assertStringContainsString('guide', $description);
    }

    public function testGetInputSchemaReturnsObjectWithNoRequiredProperties(): void
    {
        $schema = $this->metadata->getInputSchema();
        
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertEmpty((array)$schema['properties']);
    }
}
