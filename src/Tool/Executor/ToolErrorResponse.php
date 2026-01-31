<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use InvalidArgumentException;
use Throwable;

final class ToolErrorResponse
{
    public static function fromThrowable(Throwable $error, array $context = []): array
    {
        $errorData = [
            'type' => $error::class,
            'message' => $error->getMessage(),
        ];

        if ($context !== []) {
            $errorData['context'] = $context;
        }

        $errorData['details'] = self::buildDetails($error);

        return [
            'success' => false,
            'error' => $errorData,
        ];
    }

    private static function buildDetails(Throwable $error): array
    {
        $details = [
            'category' => $error instanceof InvalidArgumentException ? 'validation' : 'runtime',
            'code' => $error->getCode(),
        ];

        $debug = self::buildDebugDetails($error);
        if ($debug !== null) {
            $details['debug'] = $debug;
        }

        return $details;
    }

    private static function buildDebugDetails(Throwable $error): ?array
    {
        $flag = $_ENV['MEMEX_ERROR_DETAIL']
            ?? $_SERVER['MEMEX_ERROR_DETAIL']
            ?? getenv('MEMEX_ERROR_DETAIL');

        $enabled = filter_var($flag, FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return null;
        }

        return [
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString(),
        ];
    }
}
