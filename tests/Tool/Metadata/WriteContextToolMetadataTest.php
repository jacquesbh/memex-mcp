<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Metadata;

use Memex\Tool\Metadata\WriteContextToolMetadata;
use PHPUnit\Framework\TestCase;

final class WriteContextToolMetadataTest extends TestCase
{
    private WriteContextToolMetadata $metadata;

    protected function setUp(): void
    {
        $this->metadata = new WriteContextToolMetadata();
    }

    public function testGetNameReturnsWriteContext(): void
    {
        $this->assertSame('write_context', $this->metadata->getName());
    }

    public function testGetDescriptionReturnsString(): void
    {
        $description = $this->metadata->getDescription();
        
        $this->assertIsString($description);
        $this->assertStringContainsString('context', $description);
    }

    public function testGetInputSchemaHasRequiredProperties(): void
    {
        $schema = $this->metadata->getInputSchema();
        
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('content', $schema['properties']);
        $this->assertArrayHasKey('tags', $schema['properties']);
        $this->assertArrayHasKey('overwrite', $schema['properties']);
    }

    public function testGetInputSchemaHasRequiredFields(): void
    {
        $schema = $this->metadata->getInputSchema();
        
        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('name', $schema['required']);
        $this->assertContains('content', $schema['required']);
    }
}
