<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Metadata;

use Memex\Tool\Metadata\SearchToolMetadata;
use PHPUnit\Framework\TestCase;

final class SearchToolMetadataTest extends TestCase
{
    private SearchToolMetadata $metadata;

    protected function setUp(): void
    {
        $this->metadata = new SearchToolMetadata();
    }

    public function testGetNameReturnsSearchKnowledgeBase(): void
    {
        $this->assertSame('search_knowledge_base', $this->metadata->getName());
    }

    public function testGetDescriptionReturnsString(): void
    {
        $description = $this->metadata->getDescription();
        
        $this->assertIsString($description);
        $this->assertStringContainsString('search', $description);
    }

    public function testGetInputSchemaHasQueryProperty(): void
    {
        $schema = $this->metadata->getInputSchema();
        
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('query', $schema['properties']);
        $this->assertSame('string', $schema['properties']['query']['type']);
    }

    public function testGetInputSchemaHasOptionalTypeAndLimit(): void
    {
        $schema = $this->metadata->getInputSchema();
        
        $this->assertArrayHasKey('type', $schema['properties']);
        $this->assertArrayHasKey('limit', $schema['properties']);
        $this->assertSame('integer', $schema['properties']['limit']['type']);
    }

    public function testGetInputSchemaRequiresQuery(): void
    {
        $schema = $this->metadata->getInputSchema();
        
        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('query', $schema['required']);
    }
}
