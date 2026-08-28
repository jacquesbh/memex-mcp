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
        protected readonly PatternCompilerService $compiler,
        protected readonly VectorService $vectorService
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
        
        $contentDir = $this->getFullContentDir();
        $filePath = $contentDir . '/' . $slug . '.md';

        $frontmatter = $this->buildFrontmatter($uuid, $title, $tags, $existing !== null);
        $fullContent = $frontmatter . "\n" . $content;

        if (!is_dir($contentDir)) {
            if (!mkdir($contentDir, 0755, true) && !is_dir($contentDir)) {
                throw new RuntimeException("Failed to create directory: {$contentDir}");
            }
        }

        $bytes = file_put_contents($filePath, $fullContent);
        if ($bytes === false) {
            throw new RuntimeException("Failed to write {$this->getContentType()} file: {$filePath}");
        }
        
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

        $contentDir = $this->getFullContentDir();
        if (is_link($contentDir)) {
            throw new RuntimeException("Invalid {$this->getContentType()} directory: symlinks are not allowed");
        }

        $filePath = $contentDir . '/' . $slug . '.md';

        $realDir = realpath($contentDir);
        if (!$realDir) {
            throw new RuntimeException("Invalid file path for {$this->getContentType()}: {$slug}");
        }
        
        if (is_link($filePath)) {
            throw new RuntimeException("Invalid file path for {$this->getContentType()}: {$slug}");
        }

        if (!file_exists($filePath)) {
            throw new RuntimeException("{$this->getContentType()} not found: {$slug}");
        }

        $realPath = realpath($filePath);
        if (!$realPath || !str_starts_with($realPath, $realDir . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("Invalid file path for {$this->getContentType()}: {$slug}");
        }
        
        $identity = $this->fileIdentity($realPath);
        $content = file_get_contents($realPath);
        if ($content === false) {
            throw new RuntimeException("Failed to read {$this->getContentType()} file: {$realPath}");
        }
        $identity['hash'] = hash('sha256', $content);
        $metadata = $this->compiler->compile($content, basename($realPath));

        $this->deleteIndexedFile($realPath, $slug, $this->getContentType(), $identity);
        
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
            try {
                $content = $file->getContents();
            } catch (RuntimeException $error) {
                $path = $file->getRealPath() ?: $file->getPathname();
                throw new RuntimeException("Failed to read {$path}", 0, $error);
            }
            $compiled = $this->compiler->compile($content, $file->getFilename());

            if (!isset($compiled['metadata']['uuid'])) {
                throw new RuntimeException(
                    "File {$file->getFilename()} missing 'uuid' in frontmatter. " .
                    "All files must have a UUID before indexing."
                );
            }

            $frontmatterType = $compiled['metadata']['type'] ?? $this->extractFrontmatterType($content);
            if ($frontmatterType !== $this->getContentType()) {
                throw new RuntimeException(
                    "File {$file->getFilename()} frontmatter type must be {$this->getContentType()}"
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

    protected function deleteIndexedFile(string $filePath, string $slug, string $contentType, array $expectedIdentity): void
    {
        $directory = dirname($filePath);
        $realDirectory = realpath($directory);
        if ($realDirectory === false || $realDirectory !== $directory || is_link($directory)) {
            throw new RuntimeException("Invalid {$contentType} directory: symlinks are not allowed");
        }

        if (is_link($filePath) || realpath($filePath) !== $filePath || !$this->matchesFileIdentity($filePath, $expectedIdentity)) {
            throw new RuntimeException("Invalid file path for {$contentType}: {$slug}");
        }

        do {
            try {
                $temporaryPath = $directory . DIRECTORY_SEPARATOR . '.memex-delete-' . bin2hex(random_bytes(16)) . '.tmp';
            } catch (\Throwable $error) {
                throw new RuntimeException("Failed to prepare deletion of {$contentType} file: {$filePath}", 0, $error);
            }
        } while (file_exists($temporaryPath) || is_link($temporaryPath));

        if (!@rename($filePath, $temporaryPath)) {
            throw new RuntimeException("Failed to delete {$contentType} file: {$filePath}");
        }

        if (!$this->matchesFileIdentity($temporaryPath, $expectedIdentity)) {
            if (!file_exists($filePath) && !is_link($filePath)) {
                @rename($temporaryPath, $filePath);
            }

            throw new RuntimeException("File changed during deletion: {$filePath}");
        }

        try {
            $this->vectorService->delete($slug, $contentType);
        } catch (\Throwable $error) {
            if (!file_exists($filePath) && !is_link($filePath)) {
                if (!@rename($temporaryPath, $filePath)) {
                    throw new RuntimeException("Failed to restore {$contentType} file after index cleanup failure: {$filePath}", 0, $error);
                }
            }

            throw $error;
        }

        if (!@unlink($temporaryPath)) {
            throw new RuntimeException("Failed to delete {$contentType} file: {$filePath}");
        }
    }

    protected function fileIdentity(string $filePath): array
    {
        $stat = @lstat($filePath);
        if ($stat === false || !is_file($filePath)) {
            throw new RuntimeException("Invalid file path: {$filePath}");
        }

        return [
            'device' => $stat['dev'],
            'inode' => $stat['ino'],
        ];
    }

    private function matchesFileIdentity(string $filePath, array $expectedIdentity): bool
    {
        $actualIdentity = $this->fileIdentity($filePath);
        if ($actualIdentity !== array_intersect_key($expectedIdentity, $actualIdentity)) {
            return false;
        }

        return !isset($expectedIdentity['hash']) || hash_file('sha256', $filePath) === $expectedIdentity['hash'];
    }

    private function extractFrontmatterType(string $content): ?string
    {
        if (preg_match('/\A---\R.*?^type:\s*["\']?([^"\'\s]+)["\']?\s*$.*?^---\s*$/ms', $content, $matches) !== 1) {
            return null;
        }

        return $matches[1];
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
        if (strpos($slug, '..') !== false || strpos($slug, '/') !== false) {
            throw new RuntimeException("Security: Path traversal detected in slug");
        }

        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            throw new InvalidArgumentException("Invalid slug format");
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
