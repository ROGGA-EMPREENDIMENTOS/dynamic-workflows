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
                '='            => $this->valuesEqual($actual, $expected),
                '!='           => ! $this->valuesEqual($actual, $expected),
                '>'            => ($cmp = $this->compareValues($actual, $expected)) !== null && $cmp > 0,
                '<'            => ($cmp = $this->compareValues($actual, $expected)) !== null && $cmp < 0,
                '>='           => ($cmp = $this->compareValues($actual, $expected)) !== null && $cmp >= 0,
                '<='           => ($cmp = $this->compareValues($actual, $expected)) !== null && $cmp <= 0,
                'like'         => $expected !== null && str_contains(
                    mb_strtolower((string) $this->normalizeValue($actual)),
                    mb_strtolower((string) $expected),
                ),
                'is_empty'     => $actual === null || $actual === '',
                'is_not_empty' => $actual !== null && $actual !== '',
                'changed'      => $changed,
                'changed_from' => $changed && $this->valuesEqual($original, $expected),
                'changed_to'   => $changed && $this->valuesEqual($actual, $expected),
                default        => true,
            };

            if (! $passes) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normaliza um valor para um escalar comparável.
     *
     * Enums viram o seu value/name, datas viram string ISO, booleanos viram
     * 1/0. Assim a comparação com o valor configurado (sempre string vinda do
     * JSON) fica consistente.
     */
    protected function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return $value;
    }

    /**
     * Compara dois valores por igualdade de forma tolerante:
     * - numérica quando ambos são numéricos (5 == "5" == "5.0");
     * - case-insensitive e ignorando espaços nas pontas para strings;
     * - null e "" são tratados como equivalentes (vazio).
     */
    protected function valuesEqual(mixed $a, mixed $b): bool
    {
        $a = $this->normalizeValue($a);
        $b = $this->normalizeValue($b);

        $aEmpty = $a === null || $a === '';
        $bEmpty = $b === null || $b === '';

        if ($aEmpty || $bEmpty) {
            return $aEmpty && $bEmpty;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b));
    }

    /**
     * Comparação ordenada (<, >, <=, >=). Retorna -1, 0 ou 1 como o operador
     * spaceship, ou null quando algum dos lados está vazio (comparação inválida).
     */
    protected function compareValues(mixed $a, mixed $b): ?int
    {
        $a = $this->normalizeValue($a);
        $b = $this->normalizeValue($b);

        if ($a === null || $a === '' || $b === null || $b === '') {
            return null;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a <=> (float) $b;
        }

        return mb_strtolower(trim((string) $a)) <=> mb_strtolower(trim((string) $b));
    }
}
