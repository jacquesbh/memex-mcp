<?php

declare(strict_types=1);

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Memex\Exception\KnowledgeBaseNotDirectoryException;
use Memex\Exception\KnowledgeBaseNotFoundException;
use Memex\Exception\KnowledgeBaseNotReadableException;
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

use Humbug\SelfUpdate\Updater;

use function Castor\io;

require_once __DIR__ . '/vendor/autoload.php';

const MEMEX_VERSION = 'development'; // This version is changed during the rebuild of the binary in the CI
const GITHUB_REPO = 'jacquesbh/memex-mcp';

#[AsTask(description: 'Start the MCP server')]
function server(
    #[AsOption(name: 'kb', description: 'Path to knowledge base directory')]
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
    #[AsOption(name: 'kb', description: 'Path to knowledge base directory')]
    ?string $knowledgeBase = null
): void
{
    $initializeStructure = function(string $kbPath): void {
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

        $gitignorePath = "{$kbPath}/.gitignore";
        $gitignoreContent = ".vectors/\n";

        if (!file_exists($gitignorePath)) {
            file_put_contents($gitignorePath, $gitignoreContent);
            io()->success("Created {$gitignorePath}");
        } else {
            $existingContent = file_get_contents($gitignorePath);
            if (!str_contains($existingContent, '.vectors/')) {
                file_put_contents($gitignorePath, $existingContent . $gitignoreContent);
                io()->success("Updated {$gitignorePath}");
            } else {
                io()->note("{$gitignorePath} already contains .vectors/");
            }
        }

        io()->success("Knowledge base initialized at: {$kbPath}");
    };

    try {
        $kbPath = ApplicationHelper::resolveKnowledgeBasePath($knowledgeBase);
        io()->note("Knowledge base already exists at: {$kbPath}");
        $initializeStructure($kbPath);
    } catch (KnowledgeBaseNotFoundException $e) {
        $kbPath = $e->realPath;
        io()->writeln("Creating knowledge base at: {$kbPath}");
        mkdir($kbPath, 0755, true);
        $initializeStructure($kbPath);
    } catch (KnowledgeBaseNotDirectoryException | KnowledgeBaseNotReadableException $e) {
        io()->error($e->getMessage());
        io()->note("Cannot initialize at path: {$e->realPath}");
    }
}

#[AsTask(description: 'Show knowledge base statistics')]
function stats(
    #[AsOption(name: 'kb', description: 'Path to knowledge base directory')]
    ?string $knowledgeBase = null
): void
{
    $kbPath = ApplicationHelper::resolveKnowledgeBasePath($knowledgeBase);

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
    #[AsOption(name: 'kb', description: 'Path to knowledge base directory')]
    ?string $knowledgeBase = null
): void
{
    $kbPath = ApplicationHelper::resolveKnowledgeBasePath($knowledgeBase);

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
}

#[AsTask(namespace: 'compile', description: 'Compile all contexts for vector search')]
function contexts(
    #[AsOption(name: 'kb', description: 'Path to knowledge base directory')]
    ?string $knowledgeBase = null
): void
{
    $kbPath = ApplicationHelper::resolveKnowledgeBasePath($knowledgeBase);

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
}

#[AsTask(description: 'Check system health and configuration')]
function doctor(
    #[AsOption(name: 'kb', description: 'Path to knowledge base directory')]
    ?string $knowledgeBase = null
): void
{
    io()->title('🏥 MEMEX System Check');

    $phpVersion = PHP_VERSION;
    io()->writeln("✓ PHP version: {$phpVersion}");

    try {
        $realKbPath = ApplicationHelper::resolveKnowledgeBasePath($knowledgeBase);
        io()->writeln("✓ Knowledge base: {$realKbPath}");
    } catch (\RuntimeException $e) {
        $kbPath = $knowledgeBase ?? ApplicationHelper::getDefaultKnowledgeBasePath();
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
        io()->warning("Some checks failed - run `castor init --kb={$kbPath}` to initialize missing directories");
    }
}

#[AsTask(description: 'Check for MEMEX updates')]
function checkUpdate(): void
{
    if (!\Phar::running(false)) {
        io()->warning('Updates only available for PHAR builds');
        io()->note('Current version: ' . MEMEX_VERSION);
        return;
    }

    io()->title('🔍 Checking for updates');
    io()->writeln('Current version: ' . MEMEX_VERSION);

    $updater = new Updater(null, false);
    $updater->setStrategy(Updater::STRATEGY_SHA256);
    $updater->getStrategy()->setPharUrl('https://github.com/' . GITHUB_REPO . '/releases/latest/download/memex');
    $updater->getStrategy()->setVersionUrl('https://github.com/' . GITHUB_REPO . '/releases/latest/download/memex.sha256');

    try {
        if ($updater->hasUpdate()) {
            io()->success('Update available!');
            io()->note('Run "memex self-update" to update');
        } else {
            io()->success('You are running the latest version');
        }
    } catch (\Exception $e) {
        $message = $e->getMessage();
        if (str_contains($message, '404') || str_contains($message, 'Not Found')) {
            io()->error('No releases found. Please check https://github.com/' . GITHUB_REPO . '/releases');
        } else {
            io()->error("Failed to check for updates: {$message}");
        }
    }
}

#[AsTask(description: 'Update MEMEX to the latest version')]
function selfUpdate(): void
{
    if (!\Phar::running(false)) {
        io()->warning('Self-update only available for PHAR builds');
        io()->note('Please pull the latest changes from git or download the latest release');
        return;
    }

    io()->title('🚀 Self-updating MEMEX');
    io()->writeln('Current version: ' . MEMEX_VERSION);

    $updater = new Updater(null, false);
    $updater->setStrategy(Updater::STRATEGY_SHA256);
    $updater->getStrategy()->setPharUrl('https://github.com/' . GITHUB_REPO . '/releases/latest/download/memex');
    $updater->getStrategy()->setVersionUrl('https://github.com/' . GITHUB_REPO . '/releases/latest/download/memex.sha256');

    try {
        if ($updater->hasUpdate()) {
            io()->writeln('Downloading update...');
            
            @$result = $updater->update();
            
            if ($result) {
                fwrite(STDOUT, "\n✅ Successfully updated!\n");
                fwrite(STDOUT, "ℹ️  The new version is ready. Run \"./memex --version\" to verify.\n\n");
                exit(0);
            } else {
                fwrite(STDERR, "\n❌ Update failed - no changes made\n\n");
                exit(1);
            }
        } else {
            io()->success('Already running the latest version');
        }
    } catch (\Exception $e) {
        $message = $e->getMessage();
        if (str_contains($message, '404') || str_contains($message, 'Not Found')) {
            io()->error('No releases found on GitHub');
            io()->note('Please download manually from: https://github.com/' . GITHUB_REPO . '/releases');
        } elseif (str_contains($message, 'zlib')) {
            io()->error('Downloaded file is corrupted or invalid');
            io()->note('This may indicate a network issue or that the release is still being processed');
            io()->note('Try again in a few minutes or download manually from: https://github.com/' . GITHUB_REPO . '/releases');
        } else {
            io()->error("Update failed: {$message}");
            io()->note('You can download manually from: https://github.com/' . GITHUB_REPO . '/releases');
        }
        exit(1);
    }
}
