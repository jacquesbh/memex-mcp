<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Metadata;

use Memex\Tool\Metadata\DeleteGuideToolMetadata;
use PHPUnit\Framework\TestCase;

final class DeleteGuideToolMetadataTest extends TestCase
{
    private DeleteGuideToolMetadata $metadata;

    protected function setUp(): void
    {
        $this->metadata = new DeleteGuideToolMetadata();
    }

    public function testGetNameReturnsDeleteGuide(): void
    {
        $this->assertSame('delete_guide', $this->metadata->getName());
    }

    public function testGetDescriptionReturnsString(): void
    {
        $description = $this->metadata->getDescription();
        
        $this->assertIsString($description);
        $this->assertStringContainsString('guide', $description);
    }

    public function testGetInputSchemaHasSlugProperty(): void
    {
        $schema = $this->metadata->getInputSchema();
        
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('slug', $schema['properties']);
        $this->assertSame('string', $schema['properties']['slug']['type']);
    }

    public function testGetInputSchemaRequiresSlug(): void
    {
        $schema = $this->metadata->getInputSchema();
        
        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('slug', $schema['required']);
    }
}
