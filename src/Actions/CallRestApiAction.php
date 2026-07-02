<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Rogga\DynamicWorkflows\Contracts\ActionHandler;
use Rogga\DynamicWorkflows\VariableResolver;

class CallRestApiAction implements ActionHandler
{
    public function __construct(protected VariableResolver $resolver) {}

    public function handle(Model $model, array $config): void
    {
        $url = $config['rest_url'] ?? null;

        if (! $url) {
            return;
        }

        $url    = $this->resolver->resolve($url, $model);
        $method = strtolower($config['rest_method'] ?? 'post');
        $body   = $this->resolveBody($model, $config);

        $request = Http::withHeaders($this->resolveHeaders($model, $config));
        $request = $this->applyAuth($request, $config);

        if (($config['rest_content_type'] ?? 'application/json') === 'application/x-www-form-urlencoded') {
            $request = $request->asForm();
        }

        $context = [
            'model'    => $model::class,
            'model_id' => $model->getKey(),
            'method'   => strtoupper($method),
            'url'      => $url,
        ];

        try {
            $response = $request->$method($url, $body);
        } catch (ConnectionException $e) {
            Log::error('[DynamicWorkflows] call_rest_api falhou ao conectar', $context + [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $context += [
            'status' => $response->status(),
            'body'   => mb_substr($response->body(), 0, 2000),
        ];

        if ($response->failed()) {
            Log::warning('[DynamicWorkflows] call_rest_api retornou erro HTTP', $context);

            return;
        }

        Log::info('[DynamicWorkflows] call_rest_api executada com sucesso', $context);
    }

    public function getLabel(): string
    {
        return 'Chamar API REST';
    }

    private function resolveBody(Model $model, array $config): array
    {
        $raw = trim($config['rest_body'] ?? '');

        if (! $raw) {
            return [];
        }

        $resolved = $this->resolver->resolve($raw, $model);
        $decoded  = json_decode($resolved, true);

        if (! is_array($decoded)) {
            Log::warning('[DynamicWorkflows] call_rest_api: corpo JSON inválido após resolver variáveis, enviando corpo vazio', [
                'model'    => $model::class,
                'model_id' => $model->getKey(),
                'json'     => mb_substr($resolved, 0, 500),
                'error'    => json_last_error_msg(),
            ]);

            return [];
        }

        return $decoded;
    }

    private function resolveHeaders(Model $model, array $config): array
    {
        $raw     = $config['rest_headers'] ?? '';
        $headers = [];

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $headers[trim($key)] = trim($this->resolver->resolve($value, $model));
        }

        return $headers;
    }

    private function applyAuth(PendingRequest $request, array $config): PendingRequest
    {
        return match ($config['rest_auth_type'] ?? 'none') {
            'bearer' => $request->withToken($config['rest_bearer_token'] ?? ''),
            'basic'  => $request->withBasicAuth(
                $config['rest_basic_username'] ?? '',
                $config['rest_basic_password'] ?? '',
            ),
            default => $request,
        };
    }
}
