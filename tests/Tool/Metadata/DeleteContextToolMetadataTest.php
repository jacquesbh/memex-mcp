<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Metadata;

use Memex\Tool\Metadata\DeleteContextToolMetadata;
use PHPUnit\Framework\TestCase;

final class DeleteContextToolMetadataTest extends TestCase
{
    private DeleteContextToolMetadata $metadata;

    protected function setUp(): void
    {
        $this->metadata = new DeleteContextToolMetadata();
    }

    public function testGetNameReturnsDeleteContext(): void
    {
        $this->assertSame('delete_context', $this->metadata->getName());
    }

    public function testGetDescriptionReturnsString(): void
    {
        $description = $this->metadata->getDescription();
        
        $this->assertIsString($description);
        $this->assertStringContainsString('context', $description);
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
