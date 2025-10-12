<?php

declare(strict_types=1);

namespace Memex\Service;

use InvalidArgumentException;
use RuntimeException;

final readonly class ConfigService
{
    private const CONFIG_FILENAME = 'memex.json';
    public function getKnowledgeBasePath(): ?string
    {
        $config = $this->loadConfig();

        return $config['knowledgeBase'] ?? null;
    }

    private function loadConfig(): array
    {
        $localConfig = $this->loadConfigFile($this->getLocalConfigPath());
        if ($localConfig !== null) {
            return $localConfig;
        }

        $globalConfig = $this->loadConfigFile($this->getGlobalConfigPath());
        if ($globalConfig !== null) {
            return $globalConfig;
        }

        return [];
    }

    private function loadConfigFile(?string $path): ?array
    {
        if ($path === null || !file_exists($path)) {
            return null;
        }

        if (!is_readable($path)) {
            throw new RuntimeException("Config file is not readable: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read config file: {$path}");
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException("Invalid JSON in config file {$path}: {$e->getMessage()}", 0, $e);
        }

        if (!is_array($data) || array_keys($data) === range(0, count($data) - 1)) {
            throw new InvalidArgumentException("Config file must contain a JSON object: {$path}");
        }

        $this->validateConfig($data, $path);

        return $data;
    }

    private function validateConfig(array $config, string $path): void
    {
        if (isset($config['knowledgeBase']) && !is_string($config['knowledgeBase'])) {
            throw new InvalidArgumentException("'knowledgeBase' must be a string in config file: {$path}");
        }
    }

    private function getLocalConfigPath(): ?string
    {
        $path = getcwd();
        if ($path === false) {
            return null;
        }

        return $path . '/' . self::CONFIG_FILENAME;
    }

    private function getGlobalConfigPath(): ?string
    {
        $home = $_SERVER['HOME'] ?? getenv('HOME');
        if ($home === false || $home === '') {
            return null;
        }

        return $home . '/.memex/' . self::CONFIG_FILENAME;
    }
}
