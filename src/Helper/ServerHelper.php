<?php

declare(strict_types=1);

namespace Memex\Helper;

use Memex\Service\ContextService;
use Memex\Service\GuideService;
use Memex\Service\PatternCompilerService;
use Memex\Service\VectorService;
use Memex\Tool\MemexToolChain;
use Psr\Log\NullLogger;
use Symfony\AI\McpSdk\Capability\ToolChain;
use Symfony\AI\McpSdk\Message\Factory;
use Symfony\AI\McpSdk\Server\JsonRpcHandler;
use Symfony\AI\McpSdk\Server\NotificationHandler\InitializedHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\InitializeHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\PingHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\ToolCallHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\ToolListHandler;
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
        
        $container->register(MemexToolChain::class)
            ->addArgument(new Reference(GuideService::class))
            ->addArgument(new Reference(ContextService::class))
            ->setPublic(true);
        
        $container->compile();
        
        return $container;
    }

    public static function createJsonRpcHandler(ToolChain $toolChain, string $version = '1.0.0'): JsonRpcHandler
    {
        return new JsonRpcHandler(
            new Factory(),
            [
                new InitializeHandler('memex', $version),
                new ToolListHandler($toolChain),
                new ToolCallHandler($toolChain),
                new PingHandler(),
            ],
            [
                new InitializedHandler(),
            ],
            new NullLogger()
        );
    }
}
