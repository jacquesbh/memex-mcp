#!/usr/bin/env php
<?php

declare(strict_types=1);

use Memex\Service\GuideService;
use Memex\Service\PatternCompilerService;
use Memex\Service\VectorService;

require_once __DIR__ . '/../vendor/autoload.php';

$knowledgeBasePath = $argv[1] ?? __DIR__ . '/../knowledge-base';

if (!is_dir($knowledgeBasePath)) {
    echo "Error: Knowledge base path does not exist: {$knowledgeBasePath}\n";
    exit(1);
}

echo "Compiling guides from: {$knowledgeBasePath}/guides/\n";

$compiler = new PatternCompilerService();
$vectorService = new VectorService($knowledgeBasePath);
$guideService = new GuideService($knowledgeBasePath, $compiler, $vectorService);

$guides = $guideService->list();

echo "✅ Successfully compiled " . count($guides) . " guides\n";
echo "📁 Output: {$knowledgeBasePath}/compiled/guides.json\n";
