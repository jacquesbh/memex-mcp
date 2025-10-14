<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Symfony\Component\Uid\Uuid;

final readonly class GenerateUuidToolExecutor
{
    public function execute(): array
    {
        return [
            'uuid' => Uuid::v4()->toString(),
        ];
    }
}
