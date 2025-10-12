<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Metadata;

use Memex\Tool\Metadata\GetGuideToolMetadata;
use PHPUnit\Framework\TestCase;

final class GetGuideToolMetadataTest extends TestCase
{
    private GetGuideToolMetadata $metadata;

    protected function setUp(): void
    {
        $this->metadata = new GetGuideToolMetadata();
    }

    public function testGetNameReturnsGetGuide(): void
    {
        $this->assertSame('get_guide', $this->metadata->getName());
    }

    public function testGetDescriptionReturnsString(): void
    {
        $description = $this->metadata->getDescription();
        
        $this->assertIsString($description);
        $this->assertStringContainsString('guide', $description);
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
