<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Rogga\DynamicWorkflows\Actions\CallRestApiAction;
use Rogga\DynamicWorkflows\Actions\CallWebhookAction;
use Rogga\DynamicWorkflows\VariableResolver;
use Rogga\DynamicWorkflows\Actions\SendEmailAction;
use Rogga\DynamicWorkflows\Actions\SendSmsAction;
use Rogga\DynamicWorkflows\Actions\SendWhatsAppAction;
use Rogga\DynamicWorkflows\Actions\UpdateFieldAction;
use Rogga\DynamicWorkflows\Livewire\WorkflowRuleForm;
use Rogga\DynamicWorkflows\Livewire\WorkflowRuleList;

class DynamicWorkflowsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/dynamic-workflows.php', 'dynamic-workflows');

        $this->app->singleton(ActionRegistry::class);
        $this->app->singleton(VariableResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'dynamic-workflows');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $this->registerDefaultActions();

        Livewire::component('dynamic-workflows.workflow-rule-list', WorkflowRuleList::class);
        Livewire::component('dynamic-workflows.workflow-rule-form', WorkflowRuleForm::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/dynamic-workflows.php' => config_path('dynamic-workflows.php'),
            ], 'dynamic-workflows-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'dynamic-workflows-migrations');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/dynamic-workflows'),
            ], 'dynamic-workflows-views');
        }
    }

    protected function registerDefaultActions(): void
    {
        $registry = $this->app->make(ActionRegistry::class);

        $registry->register('send_email',    $this->app->make(SendEmailAction::class));
        $registry->register('send_whatsapp', $this->app->make(SendWhatsAppAction::class));
        $registry->register('send_sms',      $this->app->make(SendSmsAction::class));
        $registry->register('call_webhook',   $this->app->make(CallWebhookAction::class));
        $registry->register('call_rest_api',  $this->app->make(CallRestApiAction::class));
        $registry->register('update_field',  $this->app->make(UpdateFieldAction::class));
    }
}
