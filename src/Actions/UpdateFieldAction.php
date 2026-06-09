<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Actions;

use Illuminate\Database\Eloquent\Model;
use Rogga\DynamicWorkflows\Contracts\ActionHandler;
use Rogga\DynamicWorkflows\VariableResolver;

class UpdateFieldAction implements ActionHandler
{
    public function __construct(protected VariableResolver $resolver) {}

    public function handle(Model $model, array $config): void
    {
        $field = $config['field_name']  ?? null;
        $value = $this->resolver->resolve($config['field_value'] ?? '', $model);

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
