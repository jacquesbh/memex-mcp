<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Symfony\Component\Uid\Uuid;

final readonly class GenerateUuidToolExecutor
{
    public function execute(): array
    {
        try {
            return [
                'success' => true,
                'uuid' => Uuid::v4()->toString(),
            ];
        } catch (\Throwable $error) {
            return ToolErrorResponse::fromThrowable($error, ['tool' => 'generate_uuid']);
        }
    }
}
