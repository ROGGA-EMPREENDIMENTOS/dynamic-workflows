<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Rogga\DynamicWorkflows\ActionRegistry;

class ProcessWorkflowActionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        protected string $modelClass,
        protected int|string $modelKey,
        protected array $actions,
    ) {}

    public function handle(ActionRegistry $registry): void
    {
        $model = ($this->modelClass)::find($this->modelKey);

        if (! $model) {
            return;
        }

        foreach ($this->actions as $actionConfig) {
            $handler = $registry->get($actionConfig['type'] ?? '');
            $handler?->handle($model, $actionConfig);
        }
    }
}
