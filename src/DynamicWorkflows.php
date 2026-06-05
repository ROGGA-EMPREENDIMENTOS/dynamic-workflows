<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows;

use Rogga\DynamicWorkflows\Contracts\ActionHandler;

class DynamicWorkflows
{
    public static function registerAction(string $key, string|ActionHandler $handler): void
    {
        $instance = is_string($handler) ? app($handler) : $handler;

        app(ActionRegistry::class)->register($key, $instance);
    }
}
