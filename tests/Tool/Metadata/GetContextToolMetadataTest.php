<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Metadata;

use Memex\Tool\Metadata\GetContextToolMetadata;
use PHPUnit\Framework\TestCase;

final class GetContextToolMetadataTest extends TestCase
{
    private GetContextToolMetadata $metadata;

    protected function setUp(): void
    {
        $this->metadata = new GetContextToolMetadata();
    }

    public function testGetNameReturnsGetContext(): void
    {
        $this->assertSame('get_context', $this->metadata->getName());
    }

    public function testGetDescriptionReturnsString(): void
    {
        $description = $this->metadata->getDescription();
        
        $this->assertIsString($description);
        $this->assertStringContainsString('context', $description);
    }

    public function testGetInputSchemaHasQueryProperty(): void
    {
        $schema = $this->metadata->getInputSchema();
        
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('query', $schema['properties']);
        $this->assertSame('string', $schema['properties']['query']['type']);
    }

    public function testGetInputSchemaRequiresQuery(): void
    {
        $schema = $this->metadata->getInputSchema();
        
        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('query', $schema['required']);
    }
}
