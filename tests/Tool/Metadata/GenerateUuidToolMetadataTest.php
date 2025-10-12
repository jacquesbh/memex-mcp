<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Metadata;

use Memex\Tool\Metadata\GenerateUuidToolMetadata;
use PHPUnit\Framework\TestCase;

final class GenerateUuidToolMetadataTest extends TestCase
{
    private GenerateUuidToolMetadata $metadata;

    protected function setUp(): void
    {
        $this->metadata = new GenerateUuidToolMetadata();
    }

    public function testGetNameReturnsGenerateUuid(): void
    {
        $this->assertSame('generate_uuid', $this->metadata->getName());
    }

    public function testGetDescriptionReturnsString(): void
    {
        $description = $this->metadata->getDescription();
        
        $this->assertIsString($description);
        $this->assertStringContainsString('UUID', $description);
        $this->assertStringContainsString('write_guide', $description);
    }

    public function testGetInputSchemaHasNoProperties(): void
    {
        $schema = $this->metadata->getInputSchema();
        
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertIsObject($schema['properties']);
        $this->assertEmpty((array)$schema['properties']);
    }

    public function testGetOutputSchemaReturnsNull(): void
    {
        $this->assertNull($this->metadata->getOutputSchema());
    }

    public function testGetTitleReturnsNull(): void
    {
        $this->assertNull($this->metadata->getTitle());
    }

    public function testGetAnnotationsReturnsNull(): void
    {
        $this->assertNull($this->metadata->getAnnotations());
    }
}
