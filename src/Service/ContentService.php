<?php

declare(strict_types=1);

namespace Memex\Service;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Finder\Finder;

abstract class ContentService
{
    public function __construct(
        private readonly string $knowledgeBasePath,
        private readonly PatternCompilerService $compiler,
        private readonly VectorService $vectorService
    ) {}

    abstract protected function getContentType(): string;
    
    abstract protected function getContentDir(): string;

    public function get(string $uuid): array
    {
        $this->validateUuid($uuid);
        
        $result = $this->vectorService->getByUuid($uuid);
        
        if (!$result) {
            throw new RuntimeException("{$this->getContentType()} not found with UUID: {$uuid}");
        }
        
        return $result['metadata'];
    }

    public function list(): array
    {
        $results = $this->vectorService->listAll($this->getContentType());
        
        return array_map(function($item) {
            $metadata = $item['metadata'] ?? [];
            return [
                'uuid' => $item['uuid'],
                'slug' => $item['slug'],
                'name' => $item['name'],
                'title' => $item['title'],
                'tags' => $item['tags'],
                'created' => $metadata['metadata']['created'] ?? null,
                'updated' => $metadata['metadata']['updated'] ?? null,
            ];
        }, $results);
    }
    
    public function search(string $query, int $limit = 5): array
    {
        return $this->vectorService->search($query, $limit);
    }

    public function write(string $uuid, string $title, string $content, array $tags = [], bool $overwrite = false): array
    {
        $this->validateUuid($uuid);
        $this->validateTitle($title);
        $this->validateContent($content);
        
        $slug = $this->slugify($title);
        $this->validateSlug($slug);
        
        $existing = $this->vectorService->getByUuid($uuid);
        
        if ($existing && !$overwrite) {
            throw new RuntimeException("Content with UUID {$uuid} already exists. Use overwrite=true to replace.");
        }
        
        if ($existing) {
            $slug = $existing['slug'];
        }
        
        $filePath = $this->getFullContentDir() . '/' . $slug . '.md';
        
        $frontmatter = $this->buildFrontmatter($uuid, $title, $tags, $existing !== null);
        $fullContent = $frontmatter . "\n" . $content;
        
        if (!is_dir($this->getFullContentDir())) {
            mkdir($this->getFullContentDir(), 0755, true);
        }
        
        file_put_contents($filePath, $fullContent);
        
        $compiled = $this->compiler->compile($fullContent, $slug . '.md');
        $this->vectorService->index($slug, $uuid, $compiled);
        
        return [
            'uuid' => $uuid,
            'slug' => $slug,
            'title' => $title,
        ];
    }

    public function delete(string $slug): array
    {
        $this->validateSlug($slug);
        
        $filePath = $this->getFullContentDir() . '/' . $slug . '.md';
        
        $realPath = realpath($filePath);
        $realDir = realpath($this->getFullContentDir());
        
        if (!$realPath || !$realDir || strpos($realPath, $realDir) !== 0) {
            throw new RuntimeException("Invalid file path for {$this->getContentType()}: {$slug}");
        }
        
        if (!file_exists($realPath)) {
            throw new RuntimeException("{$this->getContentType()} not found: {$slug}");
        }
        
        $content = file_get_contents($realPath);
        $metadata = $this->compiler->compile($content, basename($realPath));
        
        unlink($realPath);
        
        $this->vectorService->delete($slug);
        
        return [
            'success' => true,
            'slug' => $slug,
            'title' => $metadata['metadata']['title'] ?? $metadata['name'],
            'type' => $this->getContentType()
        ];
    }

    public function reindexAll(bool $onlyNew = false): int
    {
        $contentDir = $this->getFullContentDir();
        
        if (!is_dir($contentDir)) {
            return 0;
        }
        
        $finder = new Finder();
        $finder->files()->in($contentDir)->name('*.md');
        
        $count = 0;
        foreach ($finder as $file) {
            $content = $file->getContents();
            $compiled = $this->compiler->compile($content, $file->getFilename());
            
            if (!isset($compiled['metadata']['uuid'])) {
                throw new RuntimeException(
                    "File {$file->getFilename()} missing 'uuid' in frontmatter. " .
                    "All files must have a UUID before indexing."
                );
            }
            
            $uuid = $compiled['metadata']['uuid'];
            $slug = $this->extractSlug($file->getFilename());
            
            $this->validateUuid($uuid);
            
            if ($onlyNew && $this->vectorService->existsByUuid($uuid)) {
                continue;
            }
            
            $this->vectorService->index($slug, $uuid, $compiled);
            $count++;
        }
        
        return $count;
    }

    protected function getFullContentDir(): string
    {
        return $this->knowledgeBasePath . '/' . $this->getContentDir();
    }

    protected function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    protected function validateTitle(string $title): void
    {
        if (!preg_match('/^[a-zA-Z0-9\s\-_]+$/', $title)) {
            throw new InvalidArgumentException("Title contains invalid characters. Only alphanumeric, spaces, hyphens and underscores allowed.");
        }
        
        if (strlen($title) > 200) {
            throw new InvalidArgumentException("Title too long (max 200 characters)");
        }
        
        if (empty(trim($title))) {
            throw new InvalidArgumentException("Title cannot be empty");
        }
    }

    protected function validateSlug(string $slug): void
    {
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            throw new InvalidArgumentException("Invalid slug format");
        }
        
        if (strpos($slug, '..') !== false || strpos($slug, '/') !== false) {
            throw new RuntimeException("Security: Path traversal detected in slug");
        }
    }

    protected function validateContent(string $content): void
    {
        if (strlen($content) > 1048576) {
            throw new InvalidArgumentException("Content too large (max 1MB)");
        }
        
        if (empty(trim($content))) {
            throw new InvalidArgumentException("Content cannot be empty");
        }
    }

    protected function buildFrontmatter(string $uuid, string $title, array $tags, bool $isUpdate): string
    {
        $now = date('Y-m-d');
        
        $frontmatter = "---\n";
        $frontmatter .= "uuid: {$uuid}\n";
        $frontmatter .= "title: " . addslashes($title) . "\n";
        $frontmatter .= "type: {$this->getContentType()}\n";
        
        if (!empty($tags)) {
            $frontmatter .= "tags: [" . implode(', ', array_map(fn($t) => addslashes($t), $tags)) . "]\n";
        }
        
        if (!$isUpdate) {
            $frontmatter .= "created: {$now}\n";
        } else {
            $frontmatter .= "updated: {$now}\n";
        }
        
        $frontmatter .= "---";
        
        return $frontmatter;
    }

    protected function extractSlug(string $filename): string
    {
        return str_replace('.md', '', $filename);
    }

    protected function validateUuid(string $uuid): void
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
            throw new InvalidArgumentException('Invalid UUID v4 format');
        }
    }
}
