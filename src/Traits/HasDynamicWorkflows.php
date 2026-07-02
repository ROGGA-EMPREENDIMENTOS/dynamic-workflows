<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Traits;

use Rogga\DynamicWorkflows\Jobs\ProcessWorkflowActionsJob;
use Rogga\DynamicWorkflows\Models\WorkflowRule;

trait HasDynamicWorkflows
{
    private static bool $processing = false;

    public static function bootHasDynamicWorkflows(): void
    {
        static::created(fn (self $model) => $model->processWorkflows('created'));
        static::updated(fn (self $model) => $model->processWorkflows('updated'));
        static::deleted(fn (self $model) => $model->processWorkflows('deleted'));
    }

    public function getWorkflowName(): string
    {
        return basename(str_replace('\\', '/', static::class));
    }

    public function getWorkflowFields(): array
    {
        return array_keys($this->getAttributes());
    }

    protected function processWorkflows(string $event): void
    {
        if (self::$processing) {
            return;
        }

        self::$processing = true;

        try {
            $rules    = WorkflowRule::query()
                ->where('model_class', static::class)
                ->where('event', $event)
                ->where('is_active', true)
                ->get();

            foreach ($rules as $rule) {
                if (! $this->evaluateConditions($rule->conditions ?? [], $event)) {
                    continue;
                }

                $actions = $rule->actions ?? [];

                if (empty($actions)) {
                    continue;
                }

                ProcessWorkflowActionsJob::dispatch(
                    static::class,
                    $this->getKey(),
                    $actions,
                );
            }
        } finally {
            self::$processing = false;
        }
    }

    protected function evaluateConditions(array $conditions, string $event): bool
    {
        foreach ($conditions as $condition) {
            $field    = $condition['field']    ?? null;
            $operator = $condition['operator'] ?? '=';
            $expected = $condition['value']    ?? null;

            if (! $field) {
                continue;
            }

            $actual = $this->getAttribute($field);

            // No evento "updated" comparamos também com o valor anterior à alteração.
            // Dentro do model event "updated" o getOriginal() ainda guarda o valor
            // antigo e wasChanged() indica se o campo foi de fato modificado.
            $original = $event === 'updated' ? $this->getOriginal($field) : null;
            $changed  = $event === 'updated' ? $this->wasChanged($field) : false;

            $passes = match ($operator) {
                '='            => $actual == $expected,
                '!='           => $actual != $expected,
                '>'            => $actual > $expected,
                '<'            => $actual < $expected,
                '>='           => $actual >= $expected,
                '<='           => $actual <= $expected,
                'like'         => $expected !== null && str_contains((string) $actual, (string) $expected),
                'is_empty'     => $actual === null || $actual === '',
                'is_not_empty' => $actual !== null && $actual !== '',
                'changed'      => $changed,
                'changed_from' => $changed && $original == $expected,
                'changed_to'   => $changed && $actual == $expected,
                default        => true,
            };

            if (! $passes) {
                return false;
            }
        }

        return true;
    }
}
