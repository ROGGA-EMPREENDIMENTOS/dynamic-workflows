<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows;

use Rogga\DynamicWorkflows\Contracts\ActionHandler;

class ActionRegistry
{
    /** @var array<string, ActionHandler> */
    protected array $handlers = [];

    public function register(string $key, ActionHandler $handler): void
    {
        $this->handlers[$key] = $handler;
    }

    public function get(string $key): ?ActionHandler
    {
        return $this->handlers[$key] ?? null;
    }

    /** @return array<string, string> */
    public function options(): array
    {
        return array_map(
            fn (ActionHandler $handler) => $handler->getLabel(),
            $this->handlers
        );
    }

    /** @return array<string, string> */
    public function enabledOptions(): array
    {
        return array_filter(
            $this->options(),
            fn (string $label, string $key) => \Rogga\DynamicWorkflows\Models\WorkflowSettings::isActionEnabled($key),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
