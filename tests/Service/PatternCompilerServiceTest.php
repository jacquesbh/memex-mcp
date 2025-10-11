<?php

declare(strict_types=1);

namespace Memex\Tests\Service;

use Memex\Service\PatternCompilerService;
use PHPUnit\Framework\TestCase;

final class PatternCompilerServiceTest extends TestCase
{
    private PatternCompilerService $compiler;

    protected function setUp(): void
    {
        $this->compiler = new PatternCompilerService();
    }

    public function testCompileExtractsName(): void
    {
        $result = $this->compiler->compile('# Test', 'test-guide.md');
        
        $this->assertSame('test guide', $result['name']);
    }

    public function testCompileExtractsNameFromFrontmatter(): void
    {
        $markdown = <<<MD
---
name: "Custom Name"
---
# Test
MD;
        
        $result = $this->compiler->compile($markdown, 'test-guide.md');
        
        $this->assertSame('"Custom Name"', $result['name']);
    }

    public function testCompileExtractsFrontmatter(): void
    {
        $markdown = <<<MD
---
title: "Test Guide"
type: guide
tags: [tag1, tag2, tag3]
created: 2025-01-10
---
# Content
MD;
        
        $result = $this->compiler->compile($markdown, 'test.md');
        
        $this->assertSame('"Test Guide"', $result['metadata']['title']);
        $this->assertSame('guide', $result['metadata']['type']);
        $this->assertIsArray($result['metadata']['tags']);
        $this->assertCount(3, $result['metadata']['tags']);
        $this->assertSame('2025-01-10', $result['metadata']['created']);
    }

    public function testCompileRemovesFrontmatterFromContent(): void
    {
        $markdown = <<<MD
---
title: "Test"
---
# Heading
Content here
MD;
        
        $result = $this->compiler->compile($markdown, 'test.md');
        
        $this->assertStringNotContainsString('---', $result['content']);
        $this->assertStringContainsString('Heading', $result['content']);
        $this->assertStringContainsString('Content here', $result['content']);
    }

    public function testCompileExtractsSections(): void
    {
        $markdown = <<<MD
# Section 1
Content 1

## Section 2
Content 2

### Section 3
Content 3
MD;
        
        $result = $this->compiler->compile($markdown, 'test.md');
        
        $this->assertCount(3, $result['sections']);
        $this->assertSame('Section 1', $result['sections'][0]['title']);
        $this->assertStringContainsString('Content 1', $result['sections'][0]['content']);
        $this->assertSame('Section 2', $result['sections'][1]['title']);
        $this->assertStringContainsString('Content 2', $result['sections'][1]['content']);
    }

    public function testCompileConvertsMarkdownToText(): void
    {
        $markdown = <<<MD
# Test
**Bold** and *italic* text with [link](url)
MD;
        
        $result = $this->compiler->compile($markdown, 'test.md');
        
        $this->assertStringContainsString('Bold', $result['content']);
        $this->assertStringContainsString('italic', $result['content']);
        $this->assertStringNotContainsString('**', $result['content']);
        $this->assertStringNotContainsString('*', $result['content']);
    }

    public function testCompileIncludesTimestamp(): void
    {
        $result = $this->compiler->compile('# Test', 'test.md');
        
        $this->assertArrayHasKey('compiled_at', $result);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result['compiled_at']);
    }

    public function testCompileIncludesFilename(): void
    {
        $result = $this->compiler->compile('# Test', 'my-guide.md');
        
        $this->assertSame('my-guide.md', $result['filename']);
    }

    public function testCompileHandlesEmptyContent(): void
    {
        $result = $this->compiler->compile('', 'test.md');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('sections', $result);
        $this->assertEmpty($result['sections']);
    }

    public function testCompileHandlesContentWithoutSections(): void
    {
        $result = $this->compiler->compile('Plain text without headings', 'test.md');
        
        $this->assertEmpty($result['sections']);
        $this->assertStringContainsString('Plain text', $result['content']);
    }
}
