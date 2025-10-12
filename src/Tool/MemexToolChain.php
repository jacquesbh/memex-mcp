<?php

declare(strict_types=1);

namespace Memex\Tool;

use Memex\Service\GuideService;
use Memex\Service\ContextService;
use Memex\Tool\Metadata\GetGuideToolMetadata;
use Memex\Tool\Metadata\ListGuidesToolMetadata;
use Memex\Tool\Metadata\WriteGuideToolMetadata;
use Memex\Tool\Metadata\DeleteGuideToolMetadata;
use Memex\Tool\Metadata\GetContextToolMetadata;
use Memex\Tool\Metadata\ListContextsToolMetadata;
use Memex\Tool\Metadata\WriteContextToolMetadata;
use Memex\Tool\Metadata\DeleteContextToolMetadata;
use Memex\Tool\Metadata\SearchToolMetadata;
use Memex\Tool\Metadata\GenerateUuidToolMetadata;
use Memex\Tool\Executor\GetGuideToolExecutor;
use Memex\Tool\Executor\ListGuidesToolExecutor;
use Memex\Tool\Executor\WriteGuideToolExecutor;
use Memex\Tool\Executor\DeleteGuideToolExecutor;
use Memex\Tool\Executor\GetContextToolExecutor;
use Memex\Tool\Executor\ListContextsToolExecutor;
use Memex\Tool\Executor\WriteContextToolExecutor;
use Memex\Tool\Executor\DeleteContextToolExecutor;
use Memex\Tool\Executor\SearchToolExecutor;
use Memex\Tool\Executor\GenerateUuidToolExecutor;
use Symfony\AI\McpSdk\Capability\ToolChain;

class MemexToolChain
{
    private readonly ToolChain $chain;
    
    public function __construct(
        GuideService $guideService,
        ContextService $contextService
    ) {
        $this->chain = new ToolChain([
            new GenerateUuidToolMetadata(),
            new GenerateUuidToolExecutor(),
            new GetGuideToolMetadata(),
            new GetGuideToolExecutor($guideService),
            new ListGuidesToolMetadata(),
            new ListGuidesToolExecutor($guideService),
            new WriteGuideToolMetadata(),
            new WriteGuideToolExecutor($guideService),
            new DeleteGuideToolMetadata(),
            new DeleteGuideToolExecutor($guideService),
            new GetContextToolMetadata(),
            new GetContextToolExecutor($contextService),
            new ListContextsToolMetadata(),
            new ListContextsToolExecutor($contextService),
            new WriteContextToolMetadata(),
            new WriteContextToolExecutor($contextService),
            new DeleteContextToolMetadata(),
            new DeleteContextToolExecutor($contextService),
            new SearchToolMetadata(),
            new SearchToolExecutor($guideService, $contextService),
        ]);
    }
    
    public function getChain(): ToolChain
    {
        return $this->chain;
    }
}
