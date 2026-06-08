<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
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
            return;
        }

        Http::withToken($apiToken)->post($apiUrl, [
            'phone'   => $to,
            'message' => $message,
        ]);
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
