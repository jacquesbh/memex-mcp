<?php

declare(strict_types=1);

namespace Memex\Tool;

use Memex\Service\ContextService;
use PhpMcp\Server\Attributes\McpTool;

class WriteContextTool
{
    public function __construct(
        private readonly ContextService $contextService
    ) {}

    #[McpTool(
        name: 'write_context',
        description: 'Write a new context (prompt/persona/conventions) to the knowledge base or update an existing one'
    )]
    public function write(
        string $uuid,
        string $name,
        string $content,
        array $tags = [],
        bool $overwrite = false
    ): array {
        try {
            $result = $this->contextService->write($uuid, $name, $content, $tags, $overwrite);
            
            return [
                'success' => true,
                'action' => $overwrite ? 'updated' : 'created',
                'uuid' => $result['uuid'],
                'name' => $name,
                'slug' => $result['slug'],
                'file' => "knowledge-base/contexts/{$result['slug']}.md",
                'tags' => $tags,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
