<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Finder\Finder;

class KnowledgeBaseService
{
    public function __construct(
        private readonly string $knowledgeBasePath,
        private readonly PatternCompilerService $compiler
    ) {}

    public function getCompiledPatternsPath(): string
    {
        return $this->knowledgeBasePath . '/compiled/patterns.json';
    }

    public function getPatternsForElement(string $elementType, ?string $framework = null): array
    {
        $compiled = $this->getOrCompilePatterns();

        $patterns = [];

        foreach ($compiled as $pattern) {
            if ($this->matchesElement($pattern, $elementType, $framework)) {
                $patterns[] = $pattern;
            }
        }

        return $patterns;
    }

    public function getAllPatterns(): array
    {
        return $this->getOrCompilePatterns();
    }

    public function recompilePatterns(): void
    {
        $this->compilePatterns(force: true);
    }

    private function getOrCompilePatterns(): array
    {
        $compiledPath = $this->getCompiledPatternsPath();

        if (file_exists($compiledPath)) {
            $content = file_get_contents($compiledPath);
            $data = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($data['patterns'])) {
                return $data['patterns'];
            }
        }

        return $this->compilePatterns();
    }

    private function compilePatterns(bool $force = false): array
    {
        $compiledPath = $this->getCompiledPatternsPath();

        if (!$force && file_exists($compiledPath)) {
            $content = file_get_contents($compiledPath);
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data['patterns'] ?? [];
            }
        }

        $patternsDir = $this->knowledgeBasePath . '/patterns';
        
        if (!is_dir($patternsDir)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()->in($patternsDir)->name('*.md');

        $patterns = [];

        foreach ($finder as $file) {
            $compiled = $this->compiler->compile($file->getContents(), $file->getFilename());
            $patterns[] = $compiled;
        }

        $compiledDir = dirname($compiledPath);
        if (!is_dir($compiledDir)) {
            mkdir($compiledDir, 0755, true);
        }

        file_put_contents(
            $compiledPath,
            json_encode([
                'compiled_at' => date('c'),
                'patterns' => $patterns,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return $patterns;
    }

    private function matchesElement(array $pattern, string $elementType, ?string $framework): bool
    {
        $elementLower = strtolower($elementType);
        $patternTypes = array_map('strtolower', $pattern['metadata']['element_types'] ?? []);

        if (!empty($patternTypes) && !in_array($elementLower, $patternTypes)) {
            return false;
        }

        if ($framework !== null) {
            $frameworkLower = strtolower($framework);
            $patternFrameworks = array_map('strtolower', $pattern['metadata']['frameworks'] ?? []);
            
            if (!empty($patternFrameworks) && !in_array($frameworkLower, $patternFrameworks)) {
                return false;
            }
        }

        return true;
    }
}
