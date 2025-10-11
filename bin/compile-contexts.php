#!/usr/bin/env php
<?php

declare(strict_types=1);

use Memex\Service\ContextService;
use Memex\Service\PatternCompilerService;
use Memex\Service\VectorService;

require_once __DIR__ . '/../vendor/autoload.php';

$knowledgeBasePath = $argv[1] ?? __DIR__ . '/../knowledge-base';

if (!is_dir($knowledgeBasePath)) {
    echo "Error: Knowledge base path does not exist: {$knowledgeBasePath}\n";
    exit(1);
}

echo "Compiling contexts from: {$knowledgeBasePath}/contexts/\n";

$compiler = new PatternCompilerService();
$vectorService = new VectorService($knowledgeBasePath);
$contextService = new ContextService($knowledgeBasePath, $compiler, $vectorService);

$contexts = $contextService->list();

echo "✅ Successfully compiled " . count($contexts) . " contexts\n";
echo "📁 Output: {$knowledgeBasePath}/compiled/contexts.json\n";
