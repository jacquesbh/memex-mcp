<?php

declare(strict_types=1);

namespace App\Service;

use Claude\Claude3Api\Client;
use Claude\Claude3Api\Config;
use Claude\Claude3Api\Exceptions\ApiException;
use Claude\Claude3Api\Responses\MessageResponse;

class ClaudeApiService
{
    private Client $client;

    public function __construct(string $apiKey)
    {
        $config = new Config(
            apiKey: $apiKey,
            model: 'claude-3-7-sonnet-latest',
            maxTokens: '8192'
        );

        $this->client = new Client($config);
    }

    public function generateGuide(
        string $elementType,
        string $requirements,
        ?string $framework = null,
        ?array $patterns = null
    ): array {
        $systemPrompt = $this->buildSystemPrompt($patterns);
        $userMessage = $this->buildUserMessage($elementType, $requirements, $framework);

        try {
            $response = $this->client->chat([
                'system' => $systemPrompt,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $userMessage,
                    ],
                ],
                'maxTokens' => 8192,
                'temperature' => 0.7,
            ]);

            return $this->parseResponse($response);
        } catch (ApiException $e) {
            throw new \RuntimeException(
                'Claude API error: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    private function buildSystemPrompt(?array $patterns): string
    {
        $prompt = <<<'PROMPT'
Tu es un expert en architecture logicielle et en génération de guides d'implémentation structurés.

Ta mission est de générer un guide d'implémentation détaillé et structuré pour des éléments UI, 
en suivant les meilleures pratiques et patterns fournis.

IMPORTANT: Tu ne génères PAS de code. Tu génères uniquement des GUIDES D'IMPLÉMENTATION textuels
qui expliquent COMMENT implémenter l'élément, pas le code lui-même.

Le guide doit contenir:
1. Analyse des besoins
2. Architecture et structure recommandée
3. Étapes d'implémentation détaillées
4. Patterns applicables
5. Contraintes et considérations
6. Checklist de validation

Ton guide sera utilisé par un LLM pour générer le code final.
PROMPT;

        if ($patterns !== null && count($patterns) > 0) {
            $prompt .= "\n\nPATTERNS DISPONIBLES:\n";
            foreach ($patterns as $pattern) {
                $prompt .= "\n--- {$pattern['name']} ---\n";
                $prompt .= $pattern['content'] . "\n";
            }
        }

        return $prompt;
    }

    private function buildUserMessage(
        string $elementType,
        string $requirements,
        ?string $framework
    ): string {
        $message = "Génère un guide d'implémentation pour:\n\n";
        $message .= "TYPE D'ÉLÉMENT: {$elementType}\n\n";
        $message .= "REQUIREMENTS:\n{$requirements}\n\n";

        if ($framework !== null) {
            $message .= "FRAMEWORK: {$framework}\n\n";
        }

        $message .= "Génère un guide structuré en JSON avec cette structure:\n";
        $message .= <<<'JSON'
{
  "element_type": "string",
  "framework": "string",
  "analysis": "string - analyse détaillée des besoins",
  "architecture": {
    "structure": "string - description de la structure",
    "components": ["string - liste des composants nécessaires"]
  },
  "implementation_steps": [
    {
      "step": 1,
      "title": "string",
      "description": "string - description détaillée",
      "considerations": ["string - points importants"]
    }
  ],
  "patterns": ["string - patterns applicables"],
  "constraints": ["string - contraintes à respecter"],
  "validation_checklist": ["string - points à vérifier"]
}
JSON;

        return $message;
    }

    private function parseResponse(MessageResponse $response): array
    {
        $content = $response->getContent();
        
        if (empty($content) || !isset($content[0]['text'])) {
            throw new \RuntimeException('Invalid response from Claude API');
        }

        $text = $content[0]['text'];
        
        $jsonStart = strpos($text, '{');
        $jsonEnd = strrpos($text, '}');
        
        if ($jsonStart === false || $jsonEnd === false) {
            throw new \RuntimeException('No JSON found in Claude response');
        }
        
        $jsonStr = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);
        $data = json_decode($jsonStr, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON from Claude: ' . json_last_error_msg());
        }

        return [
            'success' => true,
            'guide' => $data,
            'raw_response' => $text,
        ];
    }
}
