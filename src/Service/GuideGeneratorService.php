<?php

declare(strict_types=1);

namespace App\Service;

class GuideGeneratorService
{
    public function __construct(
        private readonly ClaudeApiService $claudeApi,
        private readonly KnowledgeBaseService $knowledgeBase
    ) {}

    public function generateGuide(
        string $elementType,
        string $requirements,
        ?string $framework = null
    ): array {
        $patterns = $this->knowledgeBase->getPatternsForElement($elementType, $framework);

        $formattedPatterns = array_map(
            fn($pattern) => [
                'name' => $pattern['name'],
                'content' => $this->formatPatternForClaude($pattern),
            ],
            $patterns
        );

        try {
            $result = $this->claudeApi->generateGuide(
                elementType: $elementType,
                requirements: $requirements,
                framework: $framework,
                patterns: $formattedPatterns
            );

            $result['patterns_used'] = count($patterns);
            $result['pattern_names'] = array_map(fn($p) => $p['name'], $patterns);

            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'fallback_guide' => $this->generateFallbackGuide($elementType, $requirements, $framework),
            ];
        }
    }

    private function formatPatternForClaude(array $pattern): string
    {
        $formatted = "PATTERN: {$pattern['name']}\n\n";

        if (!empty($pattern['metadata'])) {
            $formatted .= "METADATA:\n";
            foreach ($pattern['metadata'] as $key => $value) {
                if (is_array($value)) {
                    $formatted .= "- {$key}: " . implode(', ', $value) . "\n";
                } else {
                    $formatted .= "- {$key}: {$value}\n";
                }
            }
            $formatted .= "\n";
        }

        if (!empty($pattern['sections'])) {
            foreach ($pattern['sections'] as $section) {
                $formatted .= "## {$section['title']}\n\n";
                $formatted .= "{$section['content']}\n\n";
            }
        } else {
            $formatted .= $pattern['content'] . "\n";
        }

        return $formatted;
    }

    private function generateFallbackGuide(
        string $elementType,
        string $requirements,
        ?string $framework
    ): array {
        return [
            'element_type' => $elementType,
            'framework' => $framework ?? 'generic',
            'analysis' => "Analyse automatique pour un {$elementType}: {$requirements}",
            'architecture' => [
                'structure' => "Structure standard pour un {$elementType}",
                'components' => [
                    'Composant principal',
                    'État et propriétés',
                    'Handlers d\'événements',
                ],
            ],
            'implementation_steps' => [
                [
                    'step' => 1,
                    'title' => 'Initialisation',
                    'description' => 'Créer la structure de base du composant',
                    'considerations' => ['Définir les props', 'Initialiser l\'état'],
                ],
                [
                    'step' => 2,
                    'title' => 'Implémentation',
                    'description' => 'Implémenter la logique métier',
                    'considerations' => ['Gérer les interactions', 'Valider les entrées'],
                ],
                [
                    'step' => 3,
                    'title' => 'Styling',
                    'description' => 'Appliquer les styles visuels',
                    'considerations' => ['Responsive design', 'Accessibilité'],
                ],
            ],
            'patterns' => [],
            'constraints' => [
                'Respecter les standards d\'accessibilité',
                'Assurer la compatibilité navigateurs',
            ],
            'validation_checklist' => [
                'Tests unitaires',
                'Tests d\'intégration',
                'Validation accessibilité',
            ],
        ];
    }
}
