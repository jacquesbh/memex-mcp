<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\GuideService;

final readonly class ListGuidesToolExecutor
{
    public function __construct(
        private GuideService $guideService
    ) {}

    public function execute(): array
    {
        try {
            $guides = $this->guideService->list();

            return [
                'success' => true,
                'how_to_display' => 'Display the list of guides in a readable format, including (important) their UUIDs and names.',
                'what_to_do_next' => 'Use the "get_guide" tool to retrieve a specific guide by its UUID.',
                'total' => count($guides),
                'guides' => $guides,
            ];
        } catch (\Throwable $error) {
            return ToolErrorResponse::fromThrowable($error, ['tool' => 'list_guides']);
        }
    }
}
