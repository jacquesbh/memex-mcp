<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\ContextService;

final readonly class ListContextsToolExecutor
{
    public function __construct(
        private ContextService $contextService
    ) {}

    public function execute(): array
    {
        try {
            $contexts = $this->contextService->list();

            return [
                'success' => true,
                'how_to_display' => 'Display the list of contexts in a readable format, including (important) their UUIDs and names.',
                'what_to_do_next' => 'Use the "get_context" tool to retrieve a specific context by its UUID.',
                'total' => count($contexts),
                'contexts' => $contexts,
            ];
        } catch (\Throwable $error) {
            return ToolErrorResponse::fromThrowable($error, ['tool' => 'list_contexts']);
        }
    }
}
