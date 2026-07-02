<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Rogga\DynamicWorkflows\Contracts\ActionHandler;
use Rogga\DynamicWorkflows\Models\WorkflowSettings;
use Rogga\DynamicWorkflows\VariableResolver;

class SendWhatsAppAction implements ActionHandler
{
    public function __construct(protected VariableResolver $resolver) {}

    public function handle(Model $model, array $config): void
    {
        $apiUrl   = WorkflowSettings::get('whatsapp_api_url') ?: config('dynamic-workflows.whatsapp.api_url');
        $apiToken = WorkflowSettings::get('whatsapp_api_token') ?: config('dynamic-workflows.whatsapp.api_token');
        $config   = $this->resolver->resolveArray($config, $model);
        $to       = $this->resolveRecipient($model, $config);
        $message  = $config['whatsapp_message'] ?? null;

        if (! $apiUrl || ! $to || ! $message) {
            Log::warning('[DynamicWorkflows] send_whatsapp ignorada: configuração incompleta', [
                'model'         => $model::class,
                'model_id'      => $model->getKey(),
                'has_api_url'   => (bool) $apiUrl,
                'has_recipient' => (bool) $to,
                'has_message'   => (bool) $message,
            ]);

            return;
        }

        $context = [
            'model'    => $model::class,
            'model_id' => $model->getKey(),
            'to'       => $to,
        ];

        try {
            $response = Http::withToken($apiToken)->post($apiUrl, [
                'phone'   => $to,
                'message' => $message,
            ]);
        } catch (ConnectionException $e) {
            Log::error('[DynamicWorkflows] send_whatsapp falhou ao conectar', $context + [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $context += [
            'status' => $response->status(),
            'body'   => mb_substr($response->body(), 0, 2000),
        ];

        if ($response->failed()) {
            Log::warning('[DynamicWorkflows] send_whatsapp retornou erro HTTP', $context);

            return;
        }

        Log::info('[DynamicWorkflows] send_whatsapp executada com sucesso', $context);
    }

    public function getLabel(): string
    {
        return 'Enviar WhatsApp';
    }

    private function resolveRecipient(Model $model, array $config): ?string
    {
        $phoneField = WorkflowSettings::get('whatsapp_user_phone_field') ?: config('dynamic-workflows.whatsapp.user_phone_field', 'phone');

        return match ($config['whatsapp_recipient_type'] ?? 'direct') {
            'direct'  => $config['whatsapp_to'] ?? null,
            'user'    => $this->userAttribute((int) ($config['whatsapp_user_id'] ?? 0), $phoneField),
            'creator' => $this->creatorAttribute($model, $config['whatsapp_creator_field'] ?? 'created_by', $phoneField),
            default   => null,
        };
    }

    private function userAttribute(int $userId, string $attribute): ?string
    {
        if (! $userId) {
            return null;
        }

        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        return $userModel::find($userId)?->getAttribute($attribute);
    }

    private function creatorAttribute(Model $model, string $creatorField, string $attribute): ?string
    {
        $userId = $model->getAttribute($creatorField);

        return $userId ? $this->userAttribute((int) $userId, $attribute) : null;
    }
}
