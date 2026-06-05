<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Rogga\DynamicWorkflows\Contracts\ActionHandler;
use Rogga\DynamicWorkflows\Mail\WorkflowMail;
use Rogga\DynamicWorkflows\VariableResolver;

class SendEmailAction implements ActionHandler
{
    public function __construct(protected VariableResolver $resolver) {}

    public function handle(Model $model, array $config): void
    {
        $config = $this->resolver->resolveArray($config, $model);
        $to     = $this->resolveRecipient($model, $config);

        if (! $to) {
            return;
        }

        Mail::to($to)->send(new WorkflowMail($config, $model));
    }

    public function getLabel(): string
    {
        return 'Enviar E-mail';
    }

    private function resolveRecipient(Model $model, array $config): ?string
    {
        return match ($config['email_recipient_type'] ?? 'direct') {
            'direct'  => $config['email_to'] ?? null,
            'user'    => $this->userAttribute((int) ($config['email_user_id'] ?? 0), 'email'),
            'creator' => $this->creatorAttribute($model, $config['email_creator_field'] ?? 'created_by', 'email'),
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
