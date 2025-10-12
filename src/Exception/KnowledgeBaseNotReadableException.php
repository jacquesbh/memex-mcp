<?php

declare(strict_types=1);

namespace Memex\Exception;

use RuntimeException;
use Throwable;

final class KnowledgeBaseNotReadableException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $realPath,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
