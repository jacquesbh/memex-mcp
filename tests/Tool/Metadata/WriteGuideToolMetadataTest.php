<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Metadata;

use Memex\Tool\Metadata\WriteGuideToolMetadata;
use PHPUnit\Framework\TestCase;

final class WriteGuideToolMetadataTest extends TestCase
{
    private WriteGuideToolMetadata $metadata;

    protected function setUp(): void
    {
        $this->metadata = new WriteGuideToolMetadata();
    }

    public function testGetNameReturnsWriteGuide(): void
    {
        $this->assertSame('write_guide', $this->metadata->getName());
    }

    public function testGetDescriptionReturnsString(): void
    {
        $description = $this->metadata->getDescription();
        
        $this->assertIsString($description);
        $this->assertStringContainsString('Write', $description);
        $this->assertStringContainsString('guide', $description);
    }

    public function testGetInputSchemaHasRequiredProperties(): void
    {
        $schema = $this->metadata->getInputSchema();
        
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('title', $schema['properties']);
        $this->assertArrayHasKey('content', $schema['properties']);
        $this->assertArrayHasKey('tags', $schema['properties']);
        $this->assertArrayHasKey('overwrite', $schema['properties']);
    }

    public function testGetInputSchemaHasRequiredFields(): void
    {
        $schema = $this->metadata->getInputSchema();
        
        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('title', $schema['required']);
        $this->assertContains('content', $schema['required']);
    }

    public function testGetInputSchemaDefinesProperTypes(): void
    {
        $schema = $this->metadata->getInputSchema();
        
        $this->assertSame('string', $schema['properties']['title']['type']);
        $this->assertSame('string', $schema['properties']['content']['type']);
        $this->assertSame('array', $schema['properties']['tags']['type']);
        $this->assertSame('boolean', $schema['properties']['overwrite']['type']);
    }
}
