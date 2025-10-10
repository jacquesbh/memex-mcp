<?php

declare(strict_types=1);

namespace App\Service;

use League\CommonMark\CommonMarkConverter;

class PatternCompilerService
{
    private CommonMarkConverter $converter;

    public function __construct()
    {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function compile(string $markdownContent, string $filename): array
    {
        $metadata = $this->extractFrontMatter($markdownContent);
        $contentWithoutFrontMatter = $this->removeFrontMatter($markdownContent);
        $htmlContent = $this->converter->convert($contentWithoutFrontMatter);
        $sections = $this->extractSections($contentWithoutFrontMatter);

        return [
            'name' => $this->extractName($filename, $metadata),
            'filename' => $filename,
            'metadata' => $metadata,
            'content' => strip_tags($htmlContent->getContent()),
            'sections' => $sections,
            'compiled_at' => date('c'),
        ];
    }

    private function extractFrontMatter(string $content): array
    {
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
            $yaml = $matches[1];
            $metadata = [];
            
            foreach (explode("\n", $yaml) as $line) {
                if (preg_match('/^(\w+):\s*(.+)$/', trim($line), $m)) {
                    $key = $m[1];
                    $value = trim($m[2]);
                    
                    if (preg_match('/^\[(.+)\]$/', $value, $arrayMatch)) {
                        $metadata[$key] = array_map('trim', explode(',', $arrayMatch[1]));
                    } else {
                        $metadata[$key] = $value;
                    }
                }
            }
            
            return $metadata;
        }

        return [];
    }

    private function removeFrontMatter(string $content): string
    {
        return preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $content);
    }

    private function extractName(string $filename, array $metadata): string
    {
        if (isset($metadata['name'])) {
            return $metadata['name'];
        }

        return str_replace(['.md', '_', '-'], ['', ' ', ' '], $filename);
    }

    private function extractSections(string $markdownContent): array
    {
        $lines = explode("\n", $markdownContent);
        $sections = [];
        $currentSection = null;
        $currentContent = [];

        foreach ($lines as $line) {
            if (preg_match('/^#+\s+(.+)$/', $line, $matches)) {
                if ($currentSection !== null) {
                    $sections[] = [
                        'title' => $currentSection,
                        'content' => trim(implode("\n", $currentContent)),
                    ];
                }

                $currentSection = trim($matches[1]);
                $currentContent = [];
            } elseif ($currentSection !== null) {
                $currentContent[] = $line;
            }
        }

        if ($currentSection !== null) {
            $sections[] = [
                'title' => $currentSection,
                'content' => trim(implode("\n", $currentContent)),
            ];
        }

        return $sections;
    }
}
