#!/usr/bin/env php
<?php

declare(strict_types=1);

use Memex\Service\ContextService;
use Memex\Service\GuideService;
use Memex\Service\PatternCompilerService;
use Memex\Service\VectorService;
use Memex\Tool\DeleteContextTool;
use Memex\Tool\DeleteGuideTool;
use Memex\Tool\GetContextTool;
use Memex\Tool\GetGuideTool;
use Memex\Tool\ListContextsTool;
use Memex\Tool\ListGuidesTool;
use Memex\Tool\SearchTool;
use Memex\Tool\WriteContextTool;
use Memex\Tool\WriteGuideTool;
use PhpMcp\Server\Defaults\BasicContainer;
use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StdioServerTransport;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__ . '/../.env');

/**
 * Resolve knowledge base path with priority chain:
 * 1. CLI argument (--knowledge-base=/path/to/kb)
 * 2. Default (__DIR__ . '/../knowledge-base')
 * 
 * @param array $options Parsed CLI options
 * @return string Absolute path to knowledge base directory
 * @throws RuntimeException if path doesn't exist or isn't accessible
 */
function resolveKnowledgeBasePath(array $options): string {
    $path = isset($options['knowledge-base']) && !empty($options['knowledge-base'])
        ? $options['knowledge-base']
        : __DIR__ . '/../knowledge-base';
    
    $resolvedPath = realpath($path);
    
    if ($resolvedPath === false) {
        throw new RuntimeException(
            "Knowledge base path does not exist: {$path}"
        );
    }
    
    if (!is_dir($resolvedPath)) {
        throw new RuntimeException(
            "Knowledge base path is not a directory: {$resolvedPath}"
        );
    }
    
    if (!is_readable($resolvedPath)) {
        throw new RuntimeException(
            "Knowledge base path is not readable: {$resolvedPath}"
        );
    }
    
    return $resolvedPath;
}

$options = getopt('', ['knowledge-base:']);
$knowledgeBasePath = resolveKnowledgeBasePath($options);

$container = new BasicContainer();
$container->set(PatternCompilerService::class, new PatternCompilerService());
$container->set(VectorService::class, new VectorService($knowledgeBasePath));
$container->set(
    GuideService::class,
    new GuideService(
        $knowledgeBasePath,
        $container->get(PatternCompilerService::class),
        $container->get(VectorService::class)
    )
);
$container->set(
    ContextService::class,
    new ContextService(
        $knowledgeBasePath,
        $container->get(PatternCompilerService::class),
        $container->get(VectorService::class)
    )
);
$container->set(
    GetGuideTool::class,
    new GetGuideTool($container->get(GuideService::class))
);
$container->set(
    GetContextTool::class,
    new GetContextTool($container->get(ContextService::class))
);
$container->set(
    ListGuidesTool::class,
    new ListGuidesTool($container->get(GuideService::class))
);
$container->set(
    ListContextsTool::class,
    new ListContextsTool($container->get(ContextService::class))
);
$container->set(
    WriteGuideTool::class,
    new WriteGuideTool($container->get(GuideService::class))
);
$container->set(
    WriteContextTool::class,
    new WriteContextTool($container->get(ContextService::class))
);
$container->set(
    DeleteGuideTool::class,
    new DeleteGuideTool($container->get(GuideService::class))
);
$container->set(
    DeleteContextTool::class,
    new DeleteContextTool($container->get(ContextService::class))
);
$container->set(
    SearchTool::class,
    new SearchTool(
        $container->get(GuideService::class),
        $container->get(ContextService::class)
    )
);

$server = Server::make()
    ->withServerInfo('memex', '1.0.0')
    ->withContainer($container)
    ->build();

$server->discover(__DIR__ . '/..', ['src']);

$transport = new StdioServerTransport();
$server->listen($transport);
