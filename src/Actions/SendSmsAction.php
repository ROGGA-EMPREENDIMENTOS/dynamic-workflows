<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
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
            return;
        }

        Http::withHeaders(['auth-key' => $apiKey])
            ->post($apiUrl, [
                'Sender'    => $sender,
                'Receivers' => $to,
                'Content'   => $message,
            ]);
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
