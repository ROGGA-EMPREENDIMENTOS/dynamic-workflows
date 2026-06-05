<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Rogga\DynamicWorkflows\Contracts\ActionHandler;
use Rogga\DynamicWorkflows\VariableResolver;

class CallWebhookAction implements ActionHandler
{
    public function __construct(protected VariableResolver $resolver) {}

    public function handle(Model $model, array $config): void
    {
        $url    = $config['webhook_url']    ?? null;
        $method = strtolower($config['webhook_method'] ?? 'post');

        if (! $url) {
            return;
        }

        $url = $this->resolver->resolve($url, $model);

        Http::$method($url, [
            'model'      => $model->getTable(),
            'model_id'   => $model->getKey(),
            'attributes' => $this->resolver->resolveArray($model->getAttributes(), $model),
        ]);
    }

    public function getLabel(): string
    {
        return 'Chamar Webhook';
    }
}
