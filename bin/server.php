#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Service\ClaudeApiService;
use App\Service\GuideGeneratorService;
use App\Service\KnowledgeBaseService;
use App\Service\PatternCompilerService;
use App\Tool\GenerateImplementationGuideTool;
use PhpMcp\Server\Defaults\BasicContainer;
use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StdioServerTransport;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__ . '/../.env');

/**
 * Resolve Claude API key with priority chain:
 * 1. CLI argument (--claude-api-key=xxx)
 * 2. Environment variable (CLAUDE_API_KEY)
 * 3. .env file (CLAUDE_API_KEY)
 * 
 * @param array $options Parsed CLI options
 * @return string API key
 * @throws RuntimeException if no API key found
 */
function resolveApiKey(array $options): string {
    if (isset($options['claude-api-key']) && !empty($options['claude-api-key'])) {
        return $options['claude-api-key'];
    }
    
    $envKey = getenv('CLAUDE_API_KEY');
    if ($envKey !== false && !empty($envKey)) {
        return $envKey;
    }
    
    if (isset($_ENV['CLAUDE_API_KEY']) && !empty($_ENV['CLAUDE_API_KEY'])) {
        return $_ENV['CLAUDE_API_KEY'];
    }
    
    throw new RuntimeException(
        'CLAUDE_API_KEY not configured. Use --claude-api-key=xxx or set in .env'
    );
}

$options = getopt('', ['claude-api-key:']);
$apiKey = resolveApiKey($options);
$knowledgeBasePath = __DIR__ . '/../knowledge-base';

$container = new BasicContainer();
$container->set(PatternCompilerService::class, new PatternCompilerService());
$container->set(ClaudeApiService::class, new ClaudeApiService($apiKey));
$container->set(
    KnowledgeBaseService::class,
    new KnowledgeBaseService(
        $knowledgeBasePath,
        $container->get(PatternCompilerService::class)
    )
);
$container->set(
    GuideGeneratorService::class,
    new GuideGeneratorService(
        $container->get(ClaudeApiService::class),
        $container->get(KnowledgeBaseService::class)
    )
);
$container->set(
    GenerateImplementationGuideTool::class,
    new GenerateImplementationGuideTool(
        $container->get(GuideGeneratorService::class)
    )
);

$server = Server::make()
    ->withServerInfo('mcp-ui-element', '1.0.0')
    ->withContainer($container)
    ->build();

$server->discover(__DIR__ . '/..', ['src']);

$transport = new StdioServerTransport();
$server->listen($transport);
