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

class SendSmsAction implements ActionHandler
{
    public function __construct(protected VariableResolver $resolver) {}

    public function handle(Model $model, array $config): void
    {
        $apiUrl  = WorkflowSettings::get('sms_api_url') ?: config('dynamic-workflows.sms.api_url');
        $apiKey  = WorkflowSettings::get('sms_api_key') ?: config('dynamic-workflows.sms.api_key');
        $config  = $this->resolver->resolveArray($config, $model);
        $to      = $this->resolveRecipient($model, $config);
        $sender  = $config['sms_sender'] ?? WorkflowSettings::get('sms_sender') ?? config('dynamic-workflows.sms.sender') ?? config('app.name');
        $message = $config['sms_message'] ?? null;

        if (! $apiUrl || ! $apiKey || ! $to || ! $message) {
            Log::warning('[DynamicWorkflows] send_sms ignorada: configuração incompleta', [
                'model'         => $model::class,
                'model_id'      => $model->getKey(),
                'has_api_url'   => (bool) $apiUrl,
                'has_api_key'   => (bool) $apiKey,
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
            $response = Http::withHeaders(['auth-key' => $apiKey])
                ->post($apiUrl, [
                    'Sender'    => $sender,
                    'Receivers' => $to,
                    'Content'   => $message,
                ]);
        } catch (ConnectionException $e) {
            Log::error('[DynamicWorkflows] send_sms falhou ao conectar', $context + [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $context += [
            'status' => $response->status(),
            'body'   => mb_substr($response->body(), 0, 2000),
        ];

        if ($response->failed()) {
            Log::warning('[DynamicWorkflows] send_sms retornou erro HTTP', $context);

            return;
        }

        Log::info('[DynamicWorkflows] send_sms executada com sucesso', $context);
    }

    public function getLabel(): string
    {
        return 'Enviar SMS';
    }

    private function resolveRecipient(Model $model, array $config): ?string
    {
        $phoneField = WorkflowSettings::get('sms_user_phone_field') ?: config('dynamic-workflows.sms.user_phone_field', 'phone');

        return match ($config['sms_recipient_type'] ?? 'direct') {
            'direct'  => $config['sms_to'] ?? null,
            'user'    => $this->userAttribute((int) ($config['sms_user_id'] ?? 0), $phoneField),
            'creator' => $this->creatorAttribute($model, $config['sms_creator_field'] ?? 'created_by', $phoneField),
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
