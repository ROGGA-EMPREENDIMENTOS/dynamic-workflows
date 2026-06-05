<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ActionHandler
{
    public function handle(Model $model, array $config): void;

    public function getLabel(): string;
}
