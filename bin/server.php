#!/usr/bin/env php
<?php

declare(strict_types=1);

use Memex\Service\ContextService;
use Memex\Service\GuideService;
use Memex\Service\PatternCompilerService;
use Memex\Service\VectorService;
use Memex\Tool\MemexToolChain;
use Symfony\AI\McpSdk\Server;
use Symfony\AI\McpSdk\Server\JsonRpcHandler;
use Symfony\AI\McpSdk\Message\Factory;
use Symfony\AI\McpSdk\Server\RequestHandler\InitializeHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\ToolListHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\ToolCallHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\PingHandler;
use Symfony\AI\McpSdk\Server\NotificationHandler\InitializedHandler;
use Symfony\AI\McpSdk\Server\Transport\Stdio\SymfonyConsoleTransport;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Dotenv\Dotenv;
use Psr\Log\NullLogger;

require_once __DIR__ . '/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__ . '/../.env');

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

$containerBuilder = new ContainerBuilder();
$containerBuilder->register(PatternCompilerService::class);
$containerBuilder->register(VectorService::class)
    ->addArgument($knowledgeBasePath);
$containerBuilder->register(GuideService::class)
    ->addArgument($knowledgeBasePath)
    ->addArgument(new Reference(PatternCompilerService::class))
    ->addArgument(new Reference(VectorService::class));
$containerBuilder->register(ContextService::class)
    ->addArgument($knowledgeBasePath)
    ->addArgument(new Reference(PatternCompilerService::class))
    ->addArgument(new Reference(VectorService::class));
$containerBuilder->register(MemexToolChain::class)
    ->addArgument(new Reference(GuideService::class))
    ->addArgument(new Reference(ContextService::class))
    ->setPublic(true);

$containerBuilder->compile();

$toolChain = $containerBuilder->get(MemexToolChain::class)->getChain();

$jsonRpcHandler = new JsonRpcHandler(
    new Factory(),
    [
        new InitializeHandler('memex', '1.0.0'),
        new ToolListHandler($toolChain),
        new ToolCallHandler($toolChain),
        new PingHandler(),
    ],
    [
        new InitializedHandler(),
    ],
    new NullLogger()
);

$transport = new SymfonyConsoleTransport(new ArgvInput(), new ConsoleOutput());
$server = new Server($jsonRpcHandler, new NullLogger());
$server->connect($transport);
