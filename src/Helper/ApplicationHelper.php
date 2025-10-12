<?php

declare(strict_types=1);

namespace Memex\Helper;

use Memex\Exception\KnowledgeBaseNotDirectoryException;
use Memex\Exception\KnowledgeBaseNotFoundException;
use Memex\Exception\KnowledgeBaseNotReadableException;
use Memex\Service\ConfigService;
use RuntimeException;
use Symfony\Component\Dotenv\Dotenv;

final readonly class ApplicationHelper
{
    public static function getDefaultKnowledgeBasePath(): string
    {
        $home = $_SERVER['HOME'] ?? getenv('HOME');
        if ($home === false || $home === '') {
            throw new RuntimeException('Unable to determine home directory');
        }
        
        return $home . '/.memex/knowledge-base';
    }

    public static function resolveKnowledgeBasePath(?string $path): string
    {
        $configService = new ConfigService();
        $configPath = $configService->getKnowledgeBasePath();
        
        $resolvedPath = $path ?? $configPath ?? self::getDefaultKnowledgeBasePath();
        
        $realPath = realpath($resolvedPath);
        if ($realPath === false) {
            throw new KnowledgeBaseNotFoundException("Knowledge base path does not exist: {$resolvedPath}", $resolvedPath);
        }
        
        if (!is_dir($realPath)) {
            throw new KnowledgeBaseNotDirectoryException("Knowledge base path is not a directory: {$realPath}", $realPath);
        }
        
        if (!is_readable($realPath)) {
            throw new KnowledgeBaseNotReadableException("Knowledge base path is not readable: {$realPath}", $realPath);
        }
        
        return $realPath;
    }

    public static function loadEnvironment(): void
    {
        $envFile = dirname(__DIR__, 2) . '/.env';
        if (file_exists($envFile)) {
            (new Dotenv())->bootEnv($envFile);
        }
    }
}
