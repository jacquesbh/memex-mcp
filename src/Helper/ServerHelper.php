<?php

declare(strict_types=1);

namespace Memex\Helper;

use Memex\Service\ContextService;
use Memex\Service\GuideService;
use Memex\Service\PatternCompilerService;
use Memex\Service\VectorService;
use Memex\Tool\Executor\GenerateUuidToolExecutor;
use Memex\Tool\Executor\GetGuideToolExecutor;
use Memex\Tool\Executor\ListGuidesToolExecutor;
use Memex\Tool\Executor\WriteGuideToolExecutor;
use Memex\Tool\Executor\DeleteGuideToolExecutor;
use Memex\Tool\Executor\GetContextToolExecutor;
use Memex\Tool\Executor\ListContextsToolExecutor;
use Memex\Tool\Executor\WriteContextToolExecutor;
use Memex\Tool\Executor\DeleteContextToolExecutor;
use Memex\Tool\Executor\SearchToolExecutor;
use Mcp\Server;
use Mcp\Capability\Registry\Container;
use Psr\Log\NullLogger;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final readonly class ServerHelper
{
    public static function buildContainer(string $kbPath): ContainerBuilder
    {
        $container = new ContainerBuilder();
        
        $chunkSize = (int) ($_ENV['OLLAMA_CHUNK_SIZE'] ?? 2000);
        $chunkOverlap = (int) ($_ENV['OLLAMA_CHUNK_OVERLAP'] ?? 200);
        $numCtx = (int) ($_ENV['OLLAMA_NUM_CTX'] ?? 512);
        
        $container->register(PatternCompilerService::class);
        
        $container->register(TextSplitTransformer::class)
            ->addArgument($chunkSize)
            ->addArgument($chunkOverlap);
        
        $container->register(VectorService::class)
            ->addArgument($kbPath)
            ->addArgument(new Reference(TextSplitTransformer::class))
            ->addArgument($numCtx);
        
        $container->register(GuideService::class)
            ->addArgument($kbPath)
            ->addArgument(new Reference(PatternCompilerService::class))
            ->addArgument(new Reference(VectorService::class))
            ->setPublic(true);
        
        $container->register(ContextService::class)
            ->addArgument($kbPath)
            ->addArgument(new Reference(PatternCompilerService::class))
            ->addArgument(new Reference(VectorService::class))
            ->setPublic(true);
        
        $container->compile();
        
        return $container;
    }

    public static function createServer(
        GuideService $guideService,
        ContextService $contextService,
        string $version = '1.0.0'
    ): Server {
        $mcpContainer = new Container();
        $mcpContainer->set(GuideService::class, $guideService);
        $mcpContainer->set(ContextService::class, $contextService);

        return Server::builder()
            ->setServerInfo('memex', $version)
            ->setContainer($mcpContainer)
            ->setLogger(new NullLogger())
            ->addTool(
                [GenerateUuidToolExecutor::class, 'execute'],
                'generate_uuid',
                'Generate a unique UUID v4 identifier. Call this before creating a new guide or context with write_guide or write_context.'
            )
            ->addTool(
                [GetGuideToolExecutor::class, 'execute'],
                'get_guide',
                'Retrieve a technical guide from the knowledge base by UUID'
            )
            ->addTool(
                [ListGuidesToolExecutor::class, 'execute'],
                'list_guides',
                'List all available guides in the knowledge base'
            )
            ->addTool(
                [WriteGuideToolExecutor::class, 'execute'],
                'write_guide',
                'Write a new guide to the knowledge base or update an existing one'
            )
            ->addTool(
                [DeleteGuideToolExecutor::class, 'execute'],
                'delete_guide',
                'Delete a guide from the knowledge base'
            )
            ->addTool(
                [GetContextToolExecutor::class, 'execute'],
                'get_context',
                'Retrieve a context (prompt/persona/conventions) from the knowledge base by UUID'
            )
            ->addTool(
                [ListContextsToolExecutor::class, 'execute'],
                'list_contexts',
                'List all available contexts in the knowledge base'
            )
            ->addTool(
                [WriteContextToolExecutor::class, 'execute'],
                'write_context',
                'Write a new context (prompt/persona/conventions) to the knowledge base or update an existing one'
            )
            ->addTool(
                [DeleteContextToolExecutor::class, 'execute'],
                'delete_context',
                'Delete a context from the knowledge base'
            )
            ->addTool(
                [SearchToolExecutor::class, 'execute'],
                'search_knowledge_base',
                'Search the knowledge base using semantic search. Searches both guides and contexts.'
            )
            ->build();
    }
}
