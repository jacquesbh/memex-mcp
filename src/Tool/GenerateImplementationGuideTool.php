<?php

declare(strict_types=1);

namespace App\Tool;

use App\Service\GuideGeneratorService;
use PhpMcp\Server\Attributes\McpTool;

class GenerateImplementationGuideTool
{
    public function __construct(
        private readonly GuideGeneratorService $guideGenerator
    ) {}

    #[McpTool(
        name: 'generate-implementation-guide',
        description: 'Generates a structured implementation guide for a UI element based on requirements and patterns using Claude AI.'
    )]
    public function generate(
        string $elementType,
        string $requirements,
        ?string $framework = null
    ): array {
        try {
            $result = $this->guideGenerator->generateGuide(
                elementType: $elementType,
                requirements: $requirements,
                framework: $framework
            );

            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'element_type' => $elementType,
                'framework' => $framework ?? 'generic',
            ];
        }
    }
}
