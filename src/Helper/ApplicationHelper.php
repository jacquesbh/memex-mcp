<?php

declare(strict_types=1);

namespace Memex\Helper;

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
        $resolvedPath = $path ?? self::getDefaultKnowledgeBasePath();
        
        $realPath = realpath($resolvedPath);
        if ($realPath === false) {
            throw new RuntimeException("Knowledge base path does not exist: {$resolvedPath}");
        }
        
        if (!is_dir($realPath)) {
            throw new RuntimeException("Knowledge base path is not a directory: {$realPath}");
        }
        
        if (!is_readable($realPath)) {
            throw new RuntimeException("Knowledge base path is not readable: {$realPath}");
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
