# Conception complète : MCP Implementation Guide Generator

## 📋 Vue d'ensemble du projet

### Objectif
Créer un serveur MCP (Model Context Protocol) en PHP/Symfony qui génère des **guides d'implémentation** structurés pour aider d'autres LLMs à créer des éléments logiciels (composants, services, entités) en respectant les contraintes et patterns du projet.

**Important :** Le serveur ne génère PAS de code directement. Il génère des "recettes" d'implémentation que d'autres LLMs suivront pour écrire le code.

### Stack technique
- **PHP** : 8.2+
- **Symfony** : 7.0 (microframework)
- **MCP SDK** : mcp/sdk (SDK officiel Symfony)
- **LLM** : Claude Sonnet 4.5 (claude-sonnet-4-20250514)
- **Transport** : STDIO (avec possibilité HTTP future)
- **Knowledge Base** : Markdown → JSON compilé

---

## 🏗️ Architecture globale

```
┌─────────────────────────────────────────────────────────────┐
│                      MCP Client (LLM)                        │
└────────────────────────┬────────────────────────────────────┘
                         │ STDIO (JSON-RPC)
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                    MCP Server (bin/mcp-server)               │
│  ┌────────────────────────────────────────────────────────┐ │
│  │         ImplementationGuideTool (MCP Tool)             │ │
│  └────────────────────┬───────────────────────────────────┘ │
└───────────────────────┼─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│                   GuideGenerator (Service)                   │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Knowledge    │  │ Prompt       │  │ Claude       │      │
│  │ BaseService  │→ │ Builder      │→ │ Service      │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│            Knowledge Base (Markdown → JSON)                  │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  patterns/*.md  →  compiled/knowledge.json             │ │
│  │  constraints/*.md                                       │ │
│  └────────────────────────────────────────────────────────┘ │
│           ▲                                                  │
│           │ Compilation via CLI                              │
│  ┌────────┴────────────────────────────────────────────┐   │
│  │    CompileKnowledgeBaseCommand                       │   │
│  │           (MarkdownParser)                           │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 Structure du projet

```
mcp-ui-element/
├── bin/
│   ├── console                           # Symfony Console CLI
│   └── mcp-server                        # Point d'entrée MCP STDIO
│
├── config/
│   ├── packages/
│   │   └── framework.yaml                # Config Symfony Framework
│   ├── services.yaml                     # Conteneur de services
│   └── routes.yaml                       # Routes HTTP (optionnel)
│
├── src/
│   ├── Command/
│   │   └── CompileKnowledgeBaseCommand.php
│   │
│   ├── Service/
│   │   ├── GuideGenerator.php            # Orchestration principale
│   │   ├── ClaudeService.php             # Intégration API Claude
│   │   ├── KnowledgeBaseService.php      # Chargement patterns/constraints
│   │   ├── PromptBuilder.php             # Construction prompts Claude
│   │   └── MarkdownParser.php            # Parser Markdown → structure
│   │
│   ├── Tool/
│   │   └── ImplementationGuideTool.php   # MCP Tool
│   │
│   ├── Exception/                        # Exceptions personnalisées
│   │   ├── PatternNotFoundException.php
│   │   ├── ClaudeApiException.php
│   │   └── InvalidMarkdownException.php
│   │
│   └── Kernel.php                        # Symfony Kernel
│
├── knowledge-base/
│   ├── patterns/
│   │   ├── react-component.md            # Pattern composant React
│   │   ├── symfony-service.md            # Pattern service Symfony
│   │   ├── entity.md                     # Pattern entité Doctrine
│   │   ├── api-endpoint.md               # Pattern endpoint API
│   │   └── custom-hook.md                # Pattern React hook
│   │
│   ├── constraints/
│   │   └── project-constraints.md        # Contraintes globales projet
│   │
│   └── compiled/
│       ├── knowledge.json                # Compilation auto (gitignored)
│       └── .gitkeep
│
├── tests/                                # Tests PHPUnit
│   ├── Unit/
│   │   ├── Service/
│   │   │   ├── MarkdownParserTest.php
│   │   │   ├── PromptBuilderTest.php
│   │   │   └── GuideGeneratorTest.php
│   │   └── Tool/
│   │       └── ImplementationGuideToolTest.php
│   │
│   └── Integration/
│       ├── CompileKnowledgeBaseCommandTest.php
│       └── McpServerTest.php
│
├── var/
│   ├── cache/                            # Cache Symfony
│   ├── log/                              # Logs
│   └── .gitkeep
│
├── .env                                  # Variables d'environnement (gitignored)
├── .env.example                          # Template .env
├── .gitignore
├── composer.json
├── composer.lock
├── phpunit.xml.dist                      # Config PHPUnit
├── README.md                             # Documentation utilisateur
└── conception.md                         # Ce fichier
```

---

## 📦 Dépendances (composer.json)

### Dépendances requises

```json
{
  "require": {
    "php": ">=8.2",
    "symfony/framework-bundle": "^7.0",
    "symfony/console": "^7.0",
    "symfony/yaml": "^7.0",
    "symfony/dotenv": "^7.0",
    "symfony/http-client": "^7.0",
    "mcp/sdk": "^1.0",
    "claude-php/claude-3-api": "^1.0",
    "league/commonmark": "^2.4"
  },
  "require-dev": {
    "symfony/var-dumper": "^7.0",
    "phpunit/phpunit": "^11.0",
    "symfony/phpunit-bridge": "^7.0"
  },
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "App\\Tests\\": "tests/"
    }
  }
}
```

### Justification des packages

- **symfony/framework-bundle** : Core Symfony (DI, Kernel, Config)
- **symfony/console** : CLI pour commandes Symfony
- **symfony/yaml** : Parsing fichiers config YAML
- **symfony/dotenv** : Chargement variables .env
- **symfony/http-client** : Client HTTP pour API Claude
- **mcp/sdk** : SDK officiel Symfony pour MCP
- **claude-php/claude-3-api** : Client PHP pour Claude API
- **league/commonmark** : Parser Markdown robuste avec AST
- **phpunit** : Framework de tests unitaires

---

## 🔧 Composants détaillés

### 1. ImplementationGuideTool (MCP Tool)

**Fichier :** `src/Tool/ImplementationGuideTool.php`

**Responsabilité :** Point d'entrée MCP. Expose l'outil `get_implementation_guide` au protocole MCP.

**Interface MCP :**
```php
namespace App\Tool;

use MCP\Tool\Tool;
use MCP\Tool\ToolInterface;
use App\Service\GuideGenerator;

class ImplementationGuideTool implements ToolInterface
{
    public function __construct(
        private GuideGenerator $guideGenerator
    ) {}
    
    public function getName(): string
    {
        return 'get_implementation_guide';
    }
    
    public function getDescription(): string
    {
        return 'Generate a detailed implementation guide for a software element based on project patterns and constraints';
    }
    
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'element_type' => [
                    'type' => 'string',
                    'description' => 'Type of element to implement (e.g., react-component, symfony-service, entity)',
                ],
                'requirements' => [
                    'type' => 'string',
                    'description' => 'Detailed requirements for what this element should do',
                ],
                'context' => [
                    'type' => 'string',
                    'description' => 'Additional project context (optional)',
                ],
                'extra_constraints' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Additional constraints beyond project defaults (optional)',
                ],
            ],
            'required' => ['element_type', 'requirements'],
        ];
    }
    
    public function execute(array $arguments): array
    {
        $guide = $this->guideGenerator->generate(
            elementType: $arguments['element_type'],
            requirements: $arguments['requirements'],
            context: $arguments['context'] ?? null,
            extraConstraints: $arguments['extra_constraints'] ?? []
        );
        
        return ['guide' => $guide];
    }
}
```

**Format de sortie :**
```json
{
  "guide": {
    "element_type": "react-component",
    "element_name": "UserProfile",
    "implementation_steps": [
      {
        "step_number": 1,
        "title": "Créer la structure du composant",
        "description": "...",
        "files_to_create": ["src/components/UserProfile/UserProfile.tsx"],
        "files_to_modify": [],
        "code_snippets": [
          {
            "file": "src/components/UserProfile/UserProfile.tsx",
            "language": "typescript",
            "content": "import React from 'react';\n...",
            "explanation": "Composant de base avec props typées"
          }
        ],
        "commands_to_run": []
      }
    ],
    "dependencies_to_install": ["react", "@types/react"],
    "constraints_applied": ["TypeScript strict mode", "PascalCase naming"],
    "testing_recommendations": "Utiliser React Testing Library...",
    "additional_notes": "..."
  }
}
```

---

### 2. GuideGenerator (Service d'orchestration)

**Fichier :** `src/Service/GuideGenerator.php`

**Responsabilité :** Orchestrer la génération de guides (récupération pattern + contraintes + appel Claude + parsing réponse).

```php
namespace App\Service;

use App\Exception\PatternNotFoundException;

class GuideGenerator
{
    public function __construct(
        private KnowledgeBaseService $knowledgeBase,
        private PromptBuilder $promptBuilder,
        private ClaudeService $claude
    ) {}
    
    public function generate(
        string $elementType,
        string $requirements,
        ?string $context = null,
        array $extraConstraints = []
    ): array {
        // 1. Récupérer le pattern depuis la knowledge base
        $pattern = $this->knowledgeBase->getPattern($elementType);
        if (!$pattern) {
            throw new PatternNotFoundException(
                "Pattern '$elementType' not found in knowledge base"
            );
        }
        
        // 2. Récupérer les contraintes globales
        $constraints = array_merge(
            $this->knowledgeBase->getConstraints(),
            $extraConstraints
        );
        
        // 3. Construire le prompt pour Claude
        $prompt = $this->promptBuilder->build(
            pattern: $pattern,
            requirements: $requirements,
            constraints: $constraints,
            context: $context
        );
        
        // 4. Appeler Claude API
        $response = $this->claude->generateGuide($prompt);
        
        // 5. Parser et structurer la réponse
        return $this->parseGuideResponse($response);
    }
    
    private function parseGuideResponse(string $response): array
    {
        // Parser la réponse JSON de Claude
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Si Claude n'a pas retourné du JSON valide,
            // essayer d'extraire le JSON du texte
            if (preg_match('/```json\s*(\{.*?\})\s*```/s', $response, $matches)) {
                $decoded = json_decode($matches[1], true);
            }
        }
        
        // Valider la structure de la réponse
        $this->validateGuideStructure($decoded);
        
        return $decoded;
    }
    
    private function validateGuideStructure(array $guide): void
    {
        $required = ['implementation_steps', 'dependencies_to_install', 'constraints_applied'];
        foreach ($required as $field) {
            if (!isset($guide[$field])) {
                throw new \InvalidArgumentException(
                    "Invalid guide structure: missing field '$field'"
                );
            }
        }
    }
}
```

---

### 3. ClaudeService (Intégration API Claude)

**Fichier :** `src/Service/ClaudeService.php`

**Responsabilité :** Communication avec l'API Claude via le package claude-php/claude-3-api.

```php
namespace App\Service;

use ClaudePhp\Client;
use App\Exception\ClaudeApiException;

class ClaudeService
{
    private Client $client;
    private string $model;
    
    public function __construct(
        string $apiKey,
        string $model = 'claude-sonnet-4-20250514'
    ) {
        $this->client = new Client($apiKey);
        $this->model = $model;
    }
    
    public function generateGuide(string $prompt): string
    {
        try {
            $response = $this->client->messages()->create([
                'model' => $this->model,
                'max_tokens' => 4096,
                'temperature' => 0.3, // Faible pour plus de cohérence
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
            ]);
            
            return $response->content[0]->text;
            
        } catch (\Exception $e) {
            throw new ClaudeApiException(
                "Failed to generate guide via Claude API: " . $e->getMessage(),
                previous: $e
            );
        }
    }
}
```

**Configuration :**
- **Modèle** : `claude-sonnet-4-20250514` (Claude Sonnet 4.5, dernière version)
- **Temperature** : 0.3 (bas pour cohérence et déterminisme)
- **Max tokens** : 4096 (suffisant pour guides détaillés)
- **API Key** : Stockée dans `.env` comme `CLAUDE_API_KEY`

---

### 4. PromptBuilder (Construction de prompts)

**Fichier :** `src/Service/PromptBuilder.php`

**Responsabilité :** Construire des prompts structurés pour Claude avec pattern + requirements + contraintes.

```php
namespace App\Service;

class PromptBuilder
{
    public function build(
        array $pattern,
        string $requirements,
        array $constraints,
        ?string $context = null
    ): string {
        $prompt = <<<PROMPT
You are an expert software architect creating implementation guides for developers.

## Your Task
Generate a step-by-step implementation guide for a **{$pattern['type']}** element.

## Element Requirements
{$requirements}

PROMPT;

        if ($context) {
            $prompt .= <<<CONTEXT

## Additional Project Context
{$context}

CONTEXT;
        }

        $prompt .= <<<PATTERN

## Pattern Template to Follow
**Framework:** {$pattern['framework']}
**Description:** {$pattern['description']}

### Implementation Steps
{$this->formatSteps($pattern['steps'])}

### Pattern Constraints
{$this->formatList($pattern['constraints'])}

### Required Dependencies
{$this->formatList($pattern['dependencies'])}

PATTERN;

        $prompt .= <<<CONSTRAINTS

## Project-Wide Constraints
{$this->formatList($constraints)}

CONSTRAINTS;

        $prompt .= <<<OUTPUT

## Required Output Format
You MUST return a valid JSON object with this exact structure:

{
  "element_type": "{$pattern['type']}",
  "element_name": "DerivedFromRequirements",
  "implementation_steps": [
    {
      "step_number": 1,
      "title": "Step title",
      "description": "Detailed description of what to do",
      "files_to_create": ["path/to/file.ts"],
      "files_to_modify": ["path/to/existing.ts"],
      "code_snippets": [
        {
          "file": "path/to/file.ts",
          "language": "typescript",
          "content": "// Complete code here",
          "explanation": "Why this code is needed"
        }
      ],
      "commands_to_run": ["npm install package"]
    }
  ],
  "dependencies_to_install": ["package-name@version"],
  "constraints_applied": ["Constraint 1", "Constraint 2"],
  "testing_recommendations": "How to test this implementation",
  "additional_notes": "Any important notes or warnings"
}

OUTPUT;

        return $prompt;
    }
    
    private function formatSteps(array $steps): string
    {
        $formatted = [];
        foreach ($steps as $i => $step) {
            $num = $i + 1;
            $formatted[] = "**Step {$num}: {$step['title']}**";
            if (!empty($step['explanation'])) {
                $formatted[] = $step['explanation'];
            }
            if (!empty($step['code'])) {
                $formatted[] = "```{$step['language']}\n{$step['code']}\n```";
            }
        }
        return implode("\n\n", $formatted);
    }
    
    private function formatList(array $items): string
    {
        return implode("\n", array_map(fn($item) => "- $item", $items));
    }
}
```

---

### 5. KnowledgeBaseService (Chargement de la base de connaissances)

**Fichier :** `src/Service/KnowledgeBaseService.php`

**Responsabilité :** Charger et fournir patterns et contraintes depuis le JSON compilé.

```php
namespace App\Service;

use App\Exception\PatternNotFoundException;

class KnowledgeBaseService
{
    private array $knowledge;
    
    public function __construct(
        private string $compiledPath
    ) {
        $this->loadKnowledge();
    }
    
    private function loadKnowledge(): void
    {
        $jsonPath = $this->compiledPath . '/knowledge.json';
        
        if (!file_exists($jsonPath)) {
            throw new \RuntimeException(
                "Knowledge base not compiled. Run: php bin/console knowledge:compile"
            );
        }
        
        $json = file_get_contents($jsonPath);
        $this->knowledge = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                "Invalid knowledge base JSON: " . json_last_error_msg()
            );
        }
    }
    
    public function getPattern(string $type): ?array
    {
        return $this->knowledge['patterns'][$type] ?? null;
    }
    
    public function getAllPatterns(): array
    {
        return $this->knowledge['patterns'] ?? [];
    }
    
    public function getConstraints(): array
    {
        return $this->knowledge['constraints']['global'] ?? [];
    }
    
    public function getCompiledAt(): string
    {
        return $this->knowledge['compiled_at'] ?? 'unknown';
    }
}
```

---

### 6. MarkdownParser (Parser Markdown → Structure)

**Fichier :** `src/Service/MarkdownParser.php`

**Responsabilité :** Parser les fichiers Markdown de la knowledge base et extraire les données structurées.

```php
namespace App\Service;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Parser\MarkdownParser as CommonMarkParser;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Inline\Text;

class MarkdownParser
{
    private CommonMarkParser $parser;
    
    public function __construct()
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $this->parser = new CommonMarkParser($environment);
    }
    
    /**
     * Parse un fichier pattern Markdown
     */
    public function parsePattern(string $markdown): array
    {
        $document = $this->parser->parse($markdown);
        
        $pattern = [
            'type' => $this->extractMetadata($markdown, 'Type'),
            'framework' => $this->extractMetadata($markdown, 'Framework'),
            'description' => $this->extractMetadata($markdown, 'Description'),
            'steps' => $this->extractSteps($document, $markdown),
            'constraints' => $this->extractList($document, 'Constraints'),
            'dependencies' => $this->extractList($document, 'Dependencies'),
        ];
        
        return $pattern;
    }
    
    /**
     * Parse un fichier constraints Markdown
     */
    public function parseConstraints(string $markdown): array
    {
        $document = $this->parser->parse($markdown);
        
        return [
            'global' => $this->extractAllListItems($document)
        ];
    }
    
    private function extractMetadata(string $markdown, string $key): string
    {
        // Extraire "**Key:** value" format
        $pattern = '/\*\*' . preg_quote($key) . ':\*\*\s*(.+?)$/m';
        if (preg_match($pattern, $markdown, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }
    
    private function extractSteps(Document $document, string $markdown): array
    {
        $steps = [];
        $lines = explode("\n", $markdown);
        
        $currentStep = null;
        $inStepsSection = false;
        
        foreach ($lines as $line) {
            // Détecter section "Implementation Steps"
            if (stripos($line, '## Implementation Steps') !== false) {
                $inStepsSection = true;
                continue;
            }
            
            // Détecter fin de section
            if ($inStepsSection && preg_match('/^## /', $line)) {
                $inStepsSection = false;
            }
            
            if (!$inStepsSection) continue;
            
            // Détecter nouveau step
            if (preg_match('/^### Step \d+: (.+)$/', $line, $matches)) {
                if ($currentStep !== null) {
                    $steps[] = $currentStep;
                }
                $currentStep = [
                    'title' => trim($matches[1]),
                    'files' => [],
                    'code' => '',
                    'language' => '',
                    'explanation' => ''
                ];
                continue;
            }
            
            if ($currentStep === null) continue;
            
            // Extraire files to create
            if (preg_match('/\*\*Files to create:\*\*\s*`(.+?)`/', $line, $matches)) {
                $currentStep['files'][] = trim($matches[1]);
            }
            
            // Extraire explanation
            if (preg_match('/\*\*Explanation:\*\*\s*(.+)$/', $line, $matches)) {
                $currentStep['explanation'] = trim($matches[1]);
            }
        }
        
        if ($currentStep !== null) {
            $steps[] = $currentStep;
        }
        
        // Extraire code blocks et les associer aux steps
        $this->attachCodeToSteps($steps, $markdown);
        
        return $steps;
    }
    
    private function attachCodeToSteps(array &$steps, string $markdown): void
    {
        // Extraire tous les code blocks avec leur position
        preg_match_all('/```(\w+)\s*\n(.*?)```/s', $markdown, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        
        foreach ($matches as $match) {
            $language = $match[1][0];
            $code = trim($match[2][0]);
            $position = $match[0][1];
            
            // Trouver le step correspondant (le dernier avant ce code block)
            for ($i = count($steps) - 1; $i >= 0; $i--) {
                $stepPosition = strpos($markdown, $steps[$i]['title']);
                if ($stepPosition !== false && $stepPosition < $position) {
                    $steps[$i]['code'] = $code;
                    $steps[$i]['language'] = $language;
                    break;
                }
            }
        }
    }
    
    private function extractList(Document $document, string $sectionName): array
    {
        // Trouver la section et extraire les items de liste
        $items = [];
        $inSection = false;
        
        foreach ($document->iterator() as $node) {
            if ($node instanceof Heading) {
                $text = $this->getNodeText($node);
                if (stripos($text, $sectionName) !== false) {
                    $inSection = true;
                    continue;
                }
                if ($inSection && $node->getLevel() <= 2) {
                    break; // Fin de section
                }
            }
            
            if ($inSection && $node instanceof Text) {
                $text = trim($node->getLiteral());
                if (!empty($text) && $text !== '-') {
                    $items[] = $text;
                }
            }
        }
        
        return array_unique($items);
    }
    
    private function extractAllListItems(Document $document): array
    {
        $items = [];
        foreach ($document->iterator() as $node) {
            if ($node instanceof Text) {
                $text = trim($node->getLiteral());
                if (!empty($text) && $text !== '-') {
                    $items[] = $text;
                }
            }
        }
        return array_unique($items);
    }
    
    private function getNodeText($node): string
    {
        $text = '';
        foreach ($node->children() as $child) {
            if ($child instanceof Text) {
                $text .= $child->getLiteral();
            }
        }
        return $text;
    }
}
```

**Note :** Ce parser utilise une approche hybride (AST + regex) pour extraire efficacement les données structurées du Markdown.

---

### 7. CompileKnowledgeBaseCommand (CLI de compilation)

**Fichier :** `src/Command/CompileKnowledgeBaseCommand.php`

**Responsabilité :** Commande CLI pour compiler les fichiers Markdown en JSON optimisé.

```php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Service\MarkdownParser;

class CompileKnowledgeBaseCommand extends Command
{
    protected static $defaultName = 'knowledge:compile';
    protected static $defaultDescription = 'Compile Markdown knowledge base to optimized JSON';
    
    public function __construct(
        private MarkdownParser $parser,
        private string $knowledgeBasePath,
        private string $compiledPath
    ) {
        parent::__construct();
    }
    
    protected function configure(): void
    {
        $this->setHelp('This command compiles Markdown files from knowledge-base/ into a single optimized JSON file');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Compiling Knowledge Base');
        
        // 1. Parser tous les patterns
        $io->section('Parsing patterns...');
        $patterns = [];
        $patternFiles = glob($this->knowledgeBasePath . '/patterns/*.md');
        
        foreach ($patternFiles as $file) {
            $io->text("  - " . basename($file));
            $content = file_get_contents($file);
            $pattern = $this->parser->parsePattern($content);
            
            if (empty($pattern['type'])) {
                $io->warning("Pattern in $file has no type, skipping");
                continue;
            }
            
            $patterns[$pattern['type']] = $pattern;
        }
        
        $io->success(sprintf('Parsed %d patterns', count($patterns)));
        
        // 2. Parser les contraintes
        $io->section('Parsing constraints...');
        $constraintsFile = $this->knowledgeBasePath . '/constraints/project-constraints.md';
        
        if (!file_exists($constraintsFile)) {
            $io->warning('No project-constraints.md found');
            $constraints = ['global' => []];
        } else {
            $content = file_get_contents($constraintsFile);
            $constraints = $this->parser->parseConstraints($content);
            $io->success(sprintf('Parsed %d constraints', count($constraints['global'])));
        }
        
        // 3. Compiler en JSON
        $io->section('Compiling to JSON...');
        $compiled = [
            'patterns' => $patterns,
            'constraints' => $constraints,
            'compiled_at' => date('c'),
            'version' => '1.0.0',
        ];
        
        $outputPath = $this->compiledPath . '/knowledge.json';
        file_put_contents(
            $outputPath,
            json_encode($compiled, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        $io->success('Knowledge base compiled successfully!');
        $io->text('Output: ' . $outputPath);
        
        return Command::SUCCESS;
    }
}
```

**Usage :**
```bash
php bin/console knowledge:compile
```

---

## 📝 Format de la Knowledge Base

### Structure des fichiers Markdown

#### Pattern file (example: `knowledge-base/patterns/react-component.md`)

```markdown
# React Component Pattern

**Type:** react-component
**Framework:** React + TypeScript
**Description:** Composant React réutilisable avec TypeScript et bonnes pratiques

## Implementation Steps

### Step 1: Créer le fichier composant
**Files to create:** `src/components/{ComponentName}/{ComponentName}.tsx`

**Explanation:** Chaque composant doit être dans son propre dossier pour faciliter l'organisation.

**Code Example:**
```tsx
import React from 'react';

interface {ComponentName}Props {
  // Props définition
}

export const {ComponentName}: React.FC<{ComponentName}Props> = (props) => {
  return (
    <div>
      {/* Contenu du composant */}
    </div>
  );
};
```

### Step 2: Créer le fichier de styles
**Files to create:** `src/components/{ComponentName}/{ComponentName}.module.css`

**Code Example:**
```css
.container {
  /* Styles du composant */
}
```

### Step 3: Créer le fichier d'export
**Files to create:** `src/components/{ComponentName}/index.ts`

**Code Example:**
```typescript
export { {ComponentName} } from './{ComponentName}';
export type { {ComponentName}Props } from './{ComponentName}';
```

## Constraints
- Utiliser TypeScript strict mode
- Nommer les composants en PascalCase
- Typer toutes les props avec une interface
- Exporter les types pour réutilisation
- Utiliser CSS Modules pour isolation des styles

## Dependencies
- react
- @types/react
```

#### Constraints file (`knowledge-base/constraints/project-constraints.md`)

```markdown
# Project Constraints

## Code Quality
- Tous les fichiers TypeScript en strict mode
- Utiliser ESLint et Prettier
- Pas de types `any`
- Documenter les fonctions publiques avec JSDoc

## Architecture
- Architecture en couches (présentation, logique, données)
- Un fichier = une responsabilité
- Pas de logique métier dans les composants UI
- Hooks personnalisés pour logique réutilisable

## Naming Conventions
- Composants React: PascalCase
- Fonctions et variables: camelCase
- Constantes: SCREAMING_SNAKE_CASE
- Fichiers: kebab-case ou PascalCase selon contexte

## Testing
- Chaque composant doit avoir des tests
- Coverage minimum: 80%
- React Testing Library pour composants
- Mocker les dépendances externes

## Dependencies
- Préférer dépendances légères
- Vérifier licences
- Utiliser pnpm
- Documenter les ajouts de dépendances

## Security
- Pas de secrets hardcodés
- Valider entrées utilisateur
- Variables d'environnement pour config sensible
```

### Format JSON compilé

```json
{
  "patterns": {
    "react-component": {
      "type": "react-component",
      "framework": "React + TypeScript",
      "description": "Composant React réutilisable...",
      "steps": [
        {
          "title": "Créer le fichier composant",
          "files": ["src/components/{ComponentName}/{ComponentName}.tsx"],
          "code": "import React from 'react';\n...",
          "language": "tsx",
          "explanation": "Chaque composant doit être..."
        }
      ],
      "constraints": [
        "Utiliser TypeScript strict mode",
        "Nommer en PascalCase"
      ],
      "dependencies": ["react", "@types/react"]
    }
  },
  "constraints": {
    "global": [
      "Tous les fichiers TypeScript en strict mode",
      "Utiliser ESLint et Prettier",
      "..."
    ]
  },
  "compiled_at": "2025-01-15T10:30:00+00:00",
  "version": "1.0.0"
}
```

---

## 🚀 Point d'entrée MCP

### bin/mcp-server

**Fichier :** `bin/mcp-server`

**Responsabilité :** Script PHP exécutable qui démarre le serveur MCP en mode STDIO.

```php
#!/usr/bin/env php
<?php

use Symfony\Component\Dotenv\Dotenv;
use App\Kernel;
use MCP\Server\StdioServerTransport;
use MCP\Server\McpServer;

require_once dirname(__DIR__).'/vendor/autoload.php';

// Charger variables d'environnement
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// Booter Symfony kernel
$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

// Récupérer le container
$container = $kernel->getContainer();

// Créer transport STDIO
$transport = new StdioServerTransport();

// Créer serveur MCP
$server = new McpServer(
    name: 'implementation-guide-generator',
    version: '1.0.0',
    transport: $transport
);

// Enregistrer notre tool
$server->addTool($container->get('app.tool.implementation_guide'));

// Démarrer le serveur (bloquant)
$server->run();
```

**Permissions :**
```bash
chmod +x bin/mcp-server
```

**Usage :**
```bash
./bin/mcp-server
```

Le serveur écoute sur STDIN/STDOUT et communique en JSON-RPC selon le protocole MCP.

---

## ⚙️ Configuration Symfony

### config/services.yaml

```yaml
parameters:
  knowledge_base_path: '%kernel.project_dir%/knowledge-base'
  compiled_path: '%kernel.project_dir%/knowledge-base/compiled'

services:
  _defaults:
    autowire: true
    autoconfigure: true

  App\:
    resource: '../src/'
    exclude:
      - '../src/Kernel.php'

  # MarkdownParser
  App\Service\MarkdownParser: ~

  # KnowledgeBaseService
  App\Service\KnowledgeBaseService:
    arguments:
      $compiledPath: '%compiled_path%'

  # ClaudeService
  App\Service\ClaudeService:
    arguments:
      $apiKey: '%env(CLAUDE_API_KEY)%'
      $model: 'claude-sonnet-4-20250514'

  # PromptBuilder
  App\Service\PromptBuilder: ~

  # GuideGenerator
  App\Service\GuideGenerator: ~

  # ImplementationGuideTool
  App\Tool\ImplementationGuideTool: ~
  app.tool.implementation_guide:
    alias: App\Tool\ImplementationGuideTool
    public: true

  # CompileKnowledgeBaseCommand
  App\Command\CompileKnowledgeBaseCommand:
    arguments:
      $knowledgeBasePath: '%knowledge_base_path%'
      $compiledPath: '%compiled_path%'
    tags:
      - { name: 'console.command' }
```

### config/packages/framework.yaml

```yaml
framework:
  secret: '%env(APP_SECRET)%'
  http_client:
    default_options:
      timeout: 30
```

### .env.example

```bash
# Symfony
APP_ENV=dev
APP_SECRET=change_me_to_random_secret

# Claude API
CLAUDE_API_KEY=your_claude_api_key_here
```

---

## 🧪 Tests

### Tests unitaires

#### tests/Unit/Service/MarkdownParserTest.php

```php
namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\MarkdownParser;

class MarkdownParserTest extends TestCase
{
    private MarkdownParser $parser;
    
    protected function setUp(): void
    {
        $this->parser = new MarkdownParser();
    }
    
    public function testParsePatternExtractsMetadata(): void
    {
        $markdown = <<<MD
**Type:** test-component
**Framework:** React
**Description:** Test description
MD;
        
        $pattern = $this->parser->parsePattern($markdown);
        
        $this->assertEquals('test-component', $pattern['type']);
        $this->assertEquals('React', $pattern['framework']);
        $this->assertEquals('Test description', $pattern['description']);
    }
    
    public function testParsePatternExtractsSteps(): void
    {
        $markdown = <<<MD
## Implementation Steps

### Step 1: Create file
**Files to create:** `src/test.ts`
**Explanation:** Test explanation

```typescript
const test = 'code';
```
MD;
        
        $pattern = $this->parser->parsePattern($markdown);
        
        $this->assertCount(1, $pattern['steps']);
        $this->assertEquals('Create file', $pattern['steps'][0]['title']);
        $this->assertContains('src/test.ts', $pattern['steps'][0]['files']);
        $this->assertEquals('typescript', $pattern['steps'][0]['language']);
    }
    
    public function testParseConstraintsExtractsAllItems(): void
    {
        $markdown = <<<MD
# Project Constraints

- Use TypeScript
- Follow linting rules
- Write tests
MD;
        
        $constraints = $this->parser->parseConstraints($markdown);
        
        $this->assertContains('Use TypeScript', $constraints['global']);
        $this->assertContains('Follow linting rules', $constraints['global']);
        $this->assertContains('Write tests', $constraints['global']);
    }
}
```

#### tests/Unit/Service/PromptBuilderTest.php

```php
namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\PromptBuilder;

class PromptBuilderTest extends TestCase
{
    private PromptBuilder $builder;
    
    protected function setUp(): void
    {
        $this->builder = new PromptBuilder();
    }
    
    public function testBuildIncludesAllSections(): void
    {
        $pattern = [
            'type' => 'test-component',
            'framework' => 'React',
            'description' => 'Test',
            'steps' => [],
            'constraints' => ['Use TypeScript'],
            'dependencies' => ['react'],
        ];
        
        $prompt = $this->builder->build(
            pattern: $pattern,
            requirements: 'Create a button',
            constraints: ['Global constraint'],
            context: 'Additional context'
        );
        
        $this->assertStringContainsString('test-component', $prompt);
        $this->assertStringContainsString('Create a button', $prompt);
        $this->assertStringContainsString('Use TypeScript', $prompt);
        $this->assertStringContainsString('Global constraint', $prompt);
        $this->assertStringContainsString('Additional context', $prompt);
        $this->assertStringContainsString('Required Output Format', $prompt);
    }
}
```

#### tests/Unit/Service/GuideGeneratorTest.php

```php
namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\GuideGenerator;
use App\Service\KnowledgeBaseService;
use App\Service\PromptBuilder;
use App\Service\ClaudeService;
use App\Exception\PatternNotFoundException;

class GuideGeneratorTest extends TestCase
{
    public function testGenerateThrowsExceptionWhenPatternNotFound(): void
    {
        $kb = $this->createMock(KnowledgeBaseService::class);
        $kb->method('getPattern')->willReturn(null);
        
        $generator = new GuideGenerator(
            $kb,
            $this->createMock(PromptBuilder::class),
            $this->createMock(ClaudeService::class)
        );
        
        $this->expectException(PatternNotFoundException::class);
        $generator->generate('non-existent', 'Requirements');
    }
    
    public function testGenerateReturnsStructuredGuide(): void
    {
        $pattern = ['type' => 'test', 'steps' => [], 'constraints' => [], 'dependencies' => []];
        
        $kb = $this->createMock(KnowledgeBaseService::class);
        $kb->method('getPattern')->willReturn($pattern);
        $kb->method('getConstraints')->willReturn([]);
        
        $pb = $this->createMock(PromptBuilder::class);
        $pb->method('build')->willReturn('prompt');
        
        $claude = $this->createMock(ClaudeService::class);
        $claude->method('generateGuide')->willReturn(json_encode([
            'implementation_steps' => [],
            'dependencies_to_install' => [],
            'constraints_applied' => []
        ]));
        
        $generator = new GuideGenerator($kb, $pb, $claude);
        $guide = $generator->generate('test', 'Requirements');
        
        $this->assertIsArray($guide);
        $this->assertArrayHasKey('implementation_steps', $guide);
    }
}
```

### Tests d'intégration

#### tests/Integration/CompileKnowledgeBaseCommandTest.php

```php
namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use App\Command\CompileKnowledgeBaseCommand;

class CompileKnowledgeBaseCommandTest extends KernelTestCase
{
    public function testCommandCompilesSuccessfully(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        
        $command = $container->get(CompileKnowledgeBaseCommand::class);
        $tester = new CommandTester($command);
        
        $exitCode = $tester->execute([]);
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('compiled successfully', $tester->getDisplay());
    }
}
```

### Configuration PHPUnit

**phpunit.xml.dist**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit Tests">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration Tests">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>

    <coverage>
        <include>
            <directory>src</directory>
        </include>
    </coverage>

    <php>
        <ini name="error_reporting" value="-1"/>
        <server name="APP_ENV" value="test"/>
        <server name="KERNEL_CLASS" value="App\Kernel"/>
    </php>
</phpunit>
```

---

## 🔄 Workflow de développement

### Phase 1: Initialisation du projet

```bash
# 1. Créer le projet
mkdir mcp-ui-element && cd mcp-ui-element

# 2. Initialiser composer
composer init

# 3. Installer les dépendances
composer require symfony/framework-bundle symfony/console symfony/yaml \
                 symfony/dotenv symfony/http-client \
                 mcp/sdk claude-php/claude-3-api league/commonmark

composer require --dev symfony/var-dumper phpunit/phpunit symfony/phpunit-bridge

# 4. Créer la structure des dossiers
mkdir -p bin config/packages src/{Command,Service,Tool,Exception} \
         knowledge-base/{patterns,constraints,compiled} tests/{Unit,Integration} var

# 5. Configurer les permissions
chmod +x bin/console bin/mcp-server

# 6. Copier .env.example vers .env et configurer
cp .env.example .env
```

### Phase 2: Développement des composants

**Ordre de développement recommandé :**

1. **MarkdownParser** : Parser de base avec tests
2. **CompileKnowledgeBaseCommand** : Commande de compilation
3. **Créer exemples Markdown** : Patterns et constraints
4. **Tester la compilation** : `php bin/console knowledge:compile`
5. **KnowledgeBaseService** : Chargement du JSON compilé
6. **PromptBuilder** : Construction des prompts
7. **ClaudeService** : Intégration Claude API
8. **GuideGenerator** : Orchestration
9. **ImplementationGuideTool** : MCP Tool
10. **bin/mcp-server** : Point d'entrée MCP
11. **Tests complets** : Unitaires + intégration

### Phase 3: Tests et validation

```bash
# 1. Compiler la knowledge base
php bin/console knowledge:compile

# 2. Lancer les tests unitaires
./vendor/bin/phpunit tests/Unit

# 3. Lancer les tests d'intégration
./vendor/bin/phpunit tests/Integration

# 4. Tester le serveur MCP manuellement
./bin/mcp-server

# (Dans un autre terminal, envoyer requête JSON-RPC)
echo '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"get_implementation_guide","arguments":{"element_type":"react-component","requirements":"Create a Button component with variants"}},"id":1}' | ./bin/mcp-server

# 5. Vérifier la réponse
```

### Phase 4: Intégration avec Claude Desktop

**Configuration dans `claude_desktop_config.json` :**

```json
{
  "mcpServers": {
    "implementation-guide": {
      "command": "/usr/bin/php",
      "args": [
        "/path/to/mcp-ui-element/bin/mcp-server"
      ],
      "env": {
        "CLAUDE_API_KEY": "your_api_key_here"
      }
    }
  }
}
```

**Test avec Claude Desktop :**
1. Redémarrer Claude Desktop
2. Vérifier que le serveur MCP apparaît dans les outils disponibles
3. Tester une requête : "Use the implementation guide tool to create a React Button component"
4. Vérifier la génération du guide

---

## 🎯 Exemples d'utilisation

### Exemple 1: Créer un composant React

**Requête MCP :**
```json
{
  "name": "get_implementation_guide",
  "arguments": {
    "element_type": "react-component",
    "requirements": "Create a Button component with three variants (primary, secondary, danger) and support for icons. The button should be fully accessible.",
    "context": "This is for a design system library used across multiple applications."
  }
}
```

**Guide généré (extrait) :**
```json
{
  "guide": {
    "element_type": "react-component",
    "element_name": "Button",
    "implementation_steps": [
      {
        "step_number": 1,
        "title": "Créer la structure du composant Button",
        "description": "Créer le fichier principal du composant avec les props typées",
        "files_to_create": ["src/components/Button/Button.tsx"],
        "code_snippets": [
          {
            "file": "src/components/Button/Button.tsx",
            "language": "typescript",
            "content": "import React from 'react';\nimport styles from './Button.module.css';\n\ntype ButtonVariant = 'primary' | 'secondary' | 'danger';\n\ninterface ButtonProps {\n  variant?: ButtonVariant;\n  icon?: React.ReactNode;\n  children: React.ReactNode;\n  onClick?: () => void;\n  disabled?: boolean;\n  'aria-label'?: string;\n}\n\nexport const Button: React.FC<ButtonProps> = ({\n  variant = 'primary',\n  icon,\n  children,\n  onClick,\n  disabled = false,\n  ...ariaProps\n}) => {\n  return (\n    <button\n      className={`${styles.button} ${styles[variant]}`}\n      onClick={onClick}\n      disabled={disabled}\n      {...ariaProps}\n    >\n      {icon && <span className={styles.icon}>{icon}</span>}\n      <span className={styles.label}>{children}</span>\n    </button>\n  );\n};",
            "explanation": "Le composant utilise TypeScript pour une sécurité de type complète. Les props ARIA sont supportées pour l'accessibilité."
          }
        ]
      }
    ],
    "dependencies_to_install": ["react@^18.0.0", "@types/react@^18.0.0"],
    "constraints_applied": [
      "TypeScript strict mode",
      "PascalCase naming",
      "CSS Modules for styling",
      "Accessibility support"
    ],
    "testing_recommendations": "Utiliser React Testing Library pour tester les trois variants, l'état disabled, et l'accessibilité (aria-label, keyboard navigation).",
    "additional_notes": "Considérer l'ajout d'une prop 'size' pour différentes tailles de bouton."
  }
}
```

### Exemple 2: Créer un service Symfony

**Requête MCP :**
```json
{
  "name": "get_implementation_guide",
  "arguments": {
    "element_type": "symfony-service",
    "requirements": "Create a UserNotificationService that sends email notifications to users. It should support templating and queuing.",
    "extra_constraints": [
      "Use Symfony Messenger for queuing",
      "Use Twig for email templates"
    ]
  }
}
```

**Guide généré (structure similaire)...**

---

## 📊 Évolutions futures

### Version 1.1: Amélioration du parsing
- Support YAML frontmatter dans Markdown
- Validation stricte du format Markdown
- Support de variables dans les patterns (`{ComponentName}`)
- Génération automatique de documentation des patterns

### Version 1.2: Base de connaissances avancée
- Migration vers PostgreSQL avec pgvector
- Recherche sémantique de patterns similaires
- Versionning des patterns
- Support multi-projets

### Version 1.3: Interface web
- Interface d'administration web pour gérer les patterns
- Éditeur Markdown avec preview
- Statistiques d'utilisation des patterns
- Validation en temps réel

### Version 2.0: Multi-LLM
- Support de plusieurs LLMs (GPT-4, Gemini, etc.)
- Comparaison des guides générés
- Fine-tuning sur les patterns du projet
- Mode "expert" avec RAG sur la documentation externe

---

## 🛡️ Sécurité et bonnes pratiques

### Secrets
- **Jamais de secrets hardcodés** dans le code
- Utiliser `.env` pour les clés API (gitignored)
- Valider les clés API au démarrage du serveur

### Validation des entrées
- Valider le `element_type` contre la liste des patterns disponibles
- Limiter la taille des `requirements` et `context`
- Sanitizer les inputs avant de les passer à Claude

### Rate limiting
- Implémenter un cache pour les guides fréquemment demandés
- Limiter le nombre d'appels API Claude par minute
- Logger les erreurs API pour monitoring

### Logging
- Logger toutes les requêtes MCP (timestamp, element_type, success/failure)
- Logger les erreurs Claude API avec contexte
- Rotation des logs automatique

---

## 📚 Documentation

### README.md (structure)

```markdown
# MCP Implementation Guide Generator

Serveur MCP qui génère des guides d'implémentation structurés pour LLMs.

## Installation

1. Cloner le repo
2. `composer install`
3. Copier `.env.example` vers `.env`
4. Configurer `CLAUDE_API_KEY`
5. Compiler la knowledge base: `php bin/console knowledge:compile`

## Usage

### Démarrer le serveur MCP
./bin/mcp-server

### Intégration Claude Desktop
[Instructions...]

### Compiler la knowledge base
php bin/console knowledge:compile

## Ajouter des patterns

1. Créer un fichier `.md` dans `knowledge-base/patterns/`
2. Suivre le format documenté
3. Recompiler: `php bin/console knowledge:compile`

## Tests

./vendor/bin/phpunit

## License

MIT
```

---

## 🎓 Résumé de l'implémentation

### Composants clés
1. **ImplementationGuideTool** : Exposition MCP
2. **GuideGenerator** : Orchestration
3. **ClaudeService** : API Claude
4. **PromptBuilder** : Construction prompts
5. **KnowledgeBaseService** : Chargement patterns
6. **MarkdownParser** : Parsing Markdown → JSON
7. **CompileKnowledgeBaseCommand** : CLI compilation

### Flux de données
```
Markdown → Parser → JSON → KnowledgeBase → Prompt → Claude → Guide
```

### Technologies
- Symfony 7 (DI, Console, Config)
- MCP SDK officiel Symfony
- Claude API (Sonnet 4.5)
- league/commonmark (Parsing Markdown)

### Tests
- Unitaires : Services individuels
- Intégration : Commande compilation, MCP server

### Workflow
1. Écrire patterns en Markdown
2. Compiler avec CLI
3. Démarrer serveur MCP
4. Utiliser via Claude Desktop
5. Générer guides d'implémentation

---

## ✅ Checklist de développement

### Phase 1: Setup
- [ ] Initialiser composer.json
- [ ] Installer dépendances
- [ ] Créer structure dossiers
- [ ] Configurer Symfony (Kernel, services.yaml, framework.yaml)
- [ ] Créer .env.example et .gitignore

### Phase 2: Knowledge Base
- [ ] Implémenter MarkdownParser
- [ ] Écrire tests MarkdownParser
- [ ] Créer patterns exemples (react-component, symfony-service, entity)
- [ ] Créer project-constraints.md
- [ ] Implémenter CompileKnowledgeBaseCommand
- [ ] Tester compilation

### Phase 3: Services Core
- [ ] Implémenter KnowledgeBaseService
- [ ] Écrire tests KnowledgeBaseService
- [ ] Implémenter PromptBuilder
- [ ] Écrire tests PromptBuilder
- [ ] Implémenter ClaudeService
- [ ] Tester manuellement ClaudeService (avec vraie API)
- [ ] Implémenter GuideGenerator
- [ ] Écrire tests GuideGenerator

### Phase 4: MCP Integration
- [ ] Implémenter ImplementationGuideTool
- [ ] Écrire tests ImplementationGuideTool
- [ ] Créer bin/mcp-server
- [ ] Tester manuellement MCP server (JSON-RPC)

### Phase 5: Tests et validation
- [ ] Lancer tous les tests unitaires
- [ ] Lancer tous les tests d'intégration
- [ ] Tester avec Claude Desktop
- [ ] Valider génération de guides pour chaque pattern
- [ ] Documenter dans README.md

### Phase 6: Polish
- [ ] Gestion erreurs complète
- [ ] Logging
- [ ] Documentation inline (PHPDoc)
- [ ] README complet avec exemples

---

## 🎉 Conclusion

Cette conception complète fournit tous les éléments nécessaires pour implémenter un serveur MCP de génération de guides d'implémentation.

**Points forts de l'architecture :**
- ✅ Séparation des responsabilités claire
- ✅ Knowledge base flexible et maintenable (Markdown)
- ✅ Compilation optimisée (JSON)
- ✅ Extensible (nouveaux patterns faciles à ajouter)
- ✅ Testable (DI, mocks, tests unitaires/intégration)
- ✅ Conforme au protocole MCP

**Prêt pour l'implémentation !** 🚀
