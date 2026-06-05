<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Actions;

use Illuminate\Database\Eloquent\Model;
use Rogga\DynamicWorkflows\Contracts\ActionHandler;

class UpdateFieldAction implements ActionHandler
{
    public function handle(Model $model, array $config): void
    {
        $field = $config['field_name']  ?? null;
        $value = $config['field_value'] ?? null;

        if (! $field) {
            return;
        }

        // Usa query builder para não disparar eventos Eloquent e evitar loop infinito
        $model->newQueryWithoutScopes()
            ->whereKey($model->getKey())
            ->update([$field => $value]);
    }

    public function getLabel(): string
    {
        return 'Alterar Campo';
    }
}
