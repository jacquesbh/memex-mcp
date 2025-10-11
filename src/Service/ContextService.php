<?php

declare(strict_types=1);

namespace Memex\Service;

class ContextService extends ContentService
{
    protected function getContentType(): string
    {
        return 'context';
    }

    protected function getContentDir(): string
    {
        return 'contexts';
    }
}
