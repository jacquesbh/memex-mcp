<?php

declare(strict_types=1);

namespace Memex\Service;

use RuntimeException;

class GuideService extends ContentService
{
    public function deleteByUuid(string $uuid): array
    {
        $this->validateUuid($uuid);

        $contentDir = $this->getFullContentDir();
        if (is_link($contentDir)) {
            throw new RuntimeException('Invalid guides directory: symlinks are not allowed');
        }

        $realDir = realpath($contentDir);
        if ($realDir === false || !is_dir($realDir)) {
            throw new RuntimeException("guide not found with UUID: {$uuid}");
        }

        $entries = scandir($realDir);
        if ($entries === false) {
            throw new RuntimeException("Failed to scan guides directory: {$realDir}");
        }

        $matches = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || pathinfo($entry, PATHINFO_EXTENSION) !== 'md') {
                continue;
            }

            $filePath = $realDir . DIRECTORY_SEPARATOR . $entry;
            if (is_link($filePath)) {
                throw new RuntimeException("Invalid guide file {$entry}: symlinks are not allowed");
            }

            $realPath = realpath($filePath);
            if ($realPath === false || !is_file($realPath) || dirname($realPath) !== $realDir) {
                throw new RuntimeException("Invalid guide file path: {$entry}");
            }

            $content = file_get_contents($realPath);
            if ($content === false) {
                throw new RuntimeException("Failed to read guide file: {$realPath}");
            }

            $compiled = $this->compiler->compile($content, $entry);
            $metadata = $compiled['metadata'] ?? null;
            if (!is_array($metadata)) {
                throw new RuntimeException("Invalid guide file {$entry}: missing frontmatter");
            }

            $fileUuid = $metadata['uuid'] ?? null;
            if (!is_string($fileUuid)) {
                throw new RuntimeException("Invalid guide file {$entry}: missing UUID in frontmatter");
            }

            try {
                $this->validateUuid($fileUuid);
            } catch (\InvalidArgumentException $error) {
                throw new RuntimeException("Invalid guide file {$entry}: invalid UUID in frontmatter", 0, $error);
            }

            if (($metadata['type'] ?? null) !== 'guide') {
                throw new RuntimeException("Invalid guide file {$entry}: frontmatter type must be guide");
            }

            $slug = pathinfo($entry, PATHINFO_FILENAME);
            $this->validateSlug($slug);

            if (strcasecmp($fileUuid, $uuid) === 0) {
                $matches[] = [
                    'path' => $realPath,
                    'slug' => $slug,
                    'title' => is_string($metadata['title'] ?? null) ? $metadata['title'] : $compiled['name'],
                ];
            }
        }

        if ($matches === []) {
            throw new RuntimeException("guide not found with UUID: {$uuid}");
        }

        if (count($matches) !== 1) {
            throw new RuntimeException("Duplicate guide UUID: {$uuid}");
        }

        $match = $matches[0];
        $this->vectorService->delete($match['slug'], 'guide');

        if (is_link($match['path']) || realpath($match['path']) !== $match['path'] || !@unlink($match['path'])) {
            throw new RuntimeException("Failed to delete guide file: {$match['path']}");
        }

        return [
            'success' => true,
            'uuid' => $uuid,
            'slug' => $match['slug'],
            'title' => $match['title'],
            'type' => 'guide',
        ];
    }

    protected function getContentType(): string
    {
        return 'guide';
    }

    protected function getContentDir(): string
    {
        return 'guides';
    }
}
