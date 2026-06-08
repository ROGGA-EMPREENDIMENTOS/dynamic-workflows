<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowSettings extends Model
{
    protected $table = 'workflow_settings';

    protected $fillable = ['data'];

    protected $casts = ['data' => 'array'];

    /** @return array<string, mixed> */
    public static function getData(): array
    {
        return static::first()?->data ?? [];
    }

    /** @return array<string, mixed> */
    public static function getFormData(): array
    {
        return array_merge(self::defaults(), static::getData());
    }

    public static function store(array $data): void
    {
        $instance = static::first() ?? new static();
        $instance->data = $data;
        $instance->save();
    }

    public static function isActionEnabled(string $actionKey): bool
    {
        $data = static::getData();

        if (empty($data)) {
            return true;
        }

        $keyMap = [
            'send_email'    => 'email_enabled',
            'send_whatsapp' => 'whatsapp_enabled',
            'send_sms'      => 'sms_enabled',
            'call_webhook'  => 'webhook_enabled',
            'update_field'  => 'update_field_enabled',
        ];

        $key = $keyMap[$actionKey] ?? null;

        if ($key === null) {
            return true;
        }

        return (bool) ($data[$key] ?? true);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return data_get(static::getData(), $key, $default);
    }

    /** @return array<string, mixed> */
    private static function defaults(): array
    {
        return [
            'email_enabled'             => true,
            'whatsapp_enabled'          => true,
            'whatsapp_api_url'          => config('dynamic-workflows.whatsapp.api_url', ''),
            'whatsapp_api_token'        => config('dynamic-workflows.whatsapp.api_token', ''),
            'whatsapp_user_phone_field' => config('dynamic-workflows.whatsapp.user_phone_field', 'phone'),
            'sms_enabled'               => true,
            'sms_api_url'               => config('dynamic-workflows.sms.api_url', 'https://sms.comtele.com.br/api/v2/send'),
            'sms_api_key'               => config('dynamic-workflows.sms.api_key', ''),
            'sms_sender'                => config('dynamic-workflows.sms.sender', ''),
            'sms_user_phone_field'      => config('dynamic-workflows.sms.user_phone_field', 'phone'),
            'webhook_enabled'           => true,
            'update_field_enabled'      => true,
        ];
    }
}
