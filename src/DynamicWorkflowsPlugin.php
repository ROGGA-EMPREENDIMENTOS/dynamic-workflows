<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Rogga\DynamicWorkflows\Filament\Resources\WorkflowRuleResource;

class DynamicWorkflowsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'dynamic-workflows';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            WorkflowRuleResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
