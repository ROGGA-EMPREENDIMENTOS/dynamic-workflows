<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            Log::warning('[DynamicWorkflows] call_webhook ignorada: URL não informada', [
                'model'    => $model::class,
                'model_id' => $model->getKey(),
            ]);

            return;
        }

        $url = $this->resolver->resolve($url, $model);

        $context = [
            'model'    => $model::class,
            'model_id' => $model->getKey(),
            'method'   => strtoupper($method),
            'url'      => $url,
        ];

        try {
            $response = Http::$method($url, [
                'model'      => $model->getTable(),
                'model_id'   => $model->getKey(),
                'attributes' => $this->resolver->resolveArray($model->getAttributes(), $model),
            ]);
        } catch (ConnectionException $e) {
            Log::error('[DynamicWorkflows] call_webhook falhou ao conectar', $context + [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $context += [
            'status' => $response->status(),
            'body'   => mb_substr($response->body(), 0, 2000),
        ];

        if ($response->failed()) {
            Log::warning('[DynamicWorkflows] call_webhook retornou erro HTTP', $context);

            return;
        }

        Log::info('[DynamicWorkflows] call_webhook executada com sucesso', $context);
    }

    public function getLabel(): string
    {
        return 'Chamar Webhook';
    }
}
