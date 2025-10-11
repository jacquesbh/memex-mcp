<?php

declare(strict_types=1);

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Memex\Helper\ApplicationHelper;
use Memex\Helper\ServerHelper;
use Memex\Service\ContextService;
use Memex\Service\GuideService;
use Memex\Service\PatternCompilerService;
use Memex\Service\VectorService;
use Memex\Tool\MemexToolChain;
use Symfony\AI\McpSdk\Server;
use Symfony\AI\McpSdk\Server\Transport\Stdio\SymfonyConsoleTransport;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Psr\Log\NullLogger;

use function Castor\io;

require_once __DIR__ . '/vendor/autoload.php';

const MEMEX_VERSION = '1.0.0';

#[AsTask(description: 'Start the MCP server')]
function server(
    #[AsOption(shortcut: 'kb', description: 'Path to knowledge base directory')]
    ?string $knowledgeBase = null
): void
{
    ApplicationHelper::loadEnvironment();
    
    $kbPath = ApplicationHelper::resolveKnowledgeBasePath($knowledgeBase);
    
    $container = ServerHelper::buildContainer($kbPath);
    $toolChain = $container->get(MemexToolChain::class)->getChain();
    
    $jsonRpcHandler = ServerHelper::createJsonRpcHandler($toolChain, MEMEX_VERSION);
    $transport = new SymfonyConsoleTransport(
        new ArgvInput(),
        new ConsoleOutput()
    );
    
    $server = new Server($jsonRpcHandler, new NullLogger());
    $server->connect($transport);
}

#[AsTask(description: 'Initialize knowledge base structure')]
function init(
    #[AsOption(description: 'Path to knowledge base directory', shortcut: 'kb')]
    ?string $knowledgeBase = null
): void
{
    $kbPath = $knowledgeBase ?? ApplicationHelper::getDefaultKnowledgeBasePath();
    
    $dirs = [
        "{$kbPath}/guides",
        "{$kbPath}/contexts",
        "{$kbPath}/.vectors",
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            io()->success("Created {$dir}");
        } else {
            io()->note("{$dir} already exists");
        }
    }
    
    io()->success("Knowledge base initialized at: {$kbPath}");
}

#[AsTask(description: 'Show knowledge base statistics')]
function stats(
    #[AsOption(description: 'Path to knowledge base directory', shortcut: 'kb')]
    ?string $knowledgeBase = null
): void
{
    $kbPath = $knowledgeBase ?? ApplicationHelper::getDefaultKnowledgeBasePath();
    
    $guidesDir = "{$kbPath}/guides";
    $contextsDir = "{$kbPath}/contexts";
    
    $guidesCount = is_dir($guidesDir) ? count(glob($guidesDir . '/*.md') ?: []) : 0;
    $contextsCount = is_dir($contextsDir) ? count(glob($contextsDir . '/*.md') ?: []) : 0;
    
    io()->title('📊 Knowledge Base Statistics');
    io()->writeln("Path: {$kbPath}");
    io()->newLine();
    io()->listing([
        "Guides: {$guidesCount}",
        "Contexts: {$contextsCount}",
    ]);
}

#[AsTask(namespace: 'compile', description: 'Compile all guides for vector search')]
function guides(
    #[AsOption(description: 'Path to knowledge base directory', shortcut: 'kb')]
    ?string $knowledgeBase = null
): void
{
    $kbPath = $knowledgeBase ?? ApplicationHelper::getDefaultKnowledgeBasePath();
    
    if (!is_dir($kbPath)) {
        io()->error("Knowledge base path does not exist: {$kbPath}");
        exit(1);
    }
    
    io()->title('🔄 Compiling guides');
    io()->text("From: {$kbPath}/guides/");
    
    $compiler = new PatternCompilerService();
    $vectorService = new VectorService($kbPath);
    $guideService = new GuideService($kbPath, $compiler, $vectorService);
    
    $guides = $guideService->list();
    
    io()->success("Successfully compiled " . count($guides) . " guides");
    io()->text("Output: {$kbPath}/compiled/guides.json");
}

#[AsTask(namespace: 'compile', description: 'Compile all contexts for vector search')]
function contexts(
    #[AsOption(description: 'Path to knowledge base directory', shortcut: 'kb')]
    ?string $knowledgeBase = null
): void
{
    $kbPath = $knowledgeBase ?? ApplicationHelper::getDefaultKnowledgeBasePath();
    
    if (!is_dir($kbPath)) {
        io()->error("Knowledge base path does not exist: {$kbPath}");
        exit(1);
    }
    
    io()->title('🔄 Compiling contexts');
    io()->text("From: {$kbPath}/contexts/");
    
    $compiler = new PatternCompilerService();
    $vectorService = new VectorService($kbPath);
    $contextService = new ContextService($kbPath, $compiler, $vectorService);
    
    $contexts = $contextService->list();
    
    io()->success("Successfully compiled " . count($contexts) . " contexts");
    io()->text("Output: {$kbPath}/compiled/contexts.json");
}

#[AsTask(description: 'Check system health and configuration')]
function doctor(
    #[AsOption(description: 'Path to knowledge base directory', shortcut: 'kb')]
    ?string $knowledgeBase = null
): void
{
    io()->title('🏥 MEMEX System Check');
    
    $phpVersion = PHP_VERSION;
    io()->writeln("✓ PHP version: {$phpVersion}");
    
    $kbPath = $knowledgeBase ?? ApplicationHelper::getDefaultKnowledgeBasePath();
    $realKbPath = realpath($kbPath);
    
    if ($realKbPath !== false) {
        io()->writeln("✓ Knowledge base: {$realKbPath}");
    } else {
        io()->writeln("✗ Knowledge base: {$kbPath} (not found)");
    }
    
    io()->newLine();
    
    $checks = [
        "{$kbPath}/guides" => is_dir("{$kbPath}/guides"),
        "{$kbPath}/contexts" => is_dir("{$kbPath}/contexts"),
        "{$kbPath}/.vectors" => is_dir("{$kbPath}/.vectors"),
    ];
    
    $allGood = true;
    foreach ($checks as $path => $exists) {
        $status = $exists ? '✓' : '✗';
        io()->writeln("{$status} {$path}");
        if (!$exists) {
            $allGood = false;
        }
    }
    
    io()->newLine();
    
    if ($allGood) {
        io()->success('System check complete - all healthy!');
    } else {
        io()->warning("Some checks failed - run `castor init --knowledge-base={$kbPath}` to initialize missing directories");
    }
}
