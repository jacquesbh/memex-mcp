<?php

declare(strict_types=1);

namespace Memex\Service;

class GuideService extends ContentService
{
    protected function getContentType(): string
    {
        return 'guide';
    }

    protected function getContentDir(): string
    {
        return 'guides';
    }
}
