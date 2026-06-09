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
                if (! $this->evaluateConditions($rule->conditions ?? [])) {
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

    protected function evaluateConditions(array $conditions): bool
    {
        foreach ($conditions as $condition) {
            $field    = $condition['field']    ?? null;
            $operator = $condition['operator'] ?? '=';
            $expected = $condition['value']    ?? null;
            $actual   = $field ? $this->getAttribute($field) : null;

            $passes = match ($operator) {
                '='     => $actual == $expected,
                '!='    => $actual != $expected,
                '>'     => $actual > $expected,
                '<'     => $actual < $expected,
                '>='    => $actual >= $expected,
                '<='    => $actual <= $expected,
                'like'  => str_contains((string) $actual, (string) $expected),
                default => true,
            };

            if (! $passes) {
                return false;
            }
        }

        return true;
    }
}
