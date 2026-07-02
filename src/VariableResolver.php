<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class VariableResolver
{
    public function resolve(string $template, Model $model): string
    {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function (array $matches) use ($model) {
            return $this->getValue(trim($matches[1]), $model);
        }, $template);
    }

    public function resolveArray(array $data, Model $model): array
    {
        array_walk_recursive($data, function (mixed &$value) use ($model) {
            if (is_string($value)) {
                $value = $this->resolve($value, $model);
            }
        });

        return $data;
    }

    private function getValue(string $path, Model $model): string
    {
        // Variáveis reservadas — não dependem do model. Cada ocorrência gera um
        // valor novo (ex: {{uuid}} usado no id de um comando Blip).
        if ($special = $this->specialValue($path)) {
            return $special;
        }

        $parts   = explode('.', $path);
        $current = $model;

        foreach ($parts as $part) {
            if ($current instanceof Model) {
                $current = method_exists($current, $part)
                    ? $current->$part
                    : $current->getAttribute($part);
            } elseif ($current instanceof Collection) {
                $current = $current->pluck($part)->filter()->implode(', ');
                break;
            } elseif (is_object($current)) {
                $current = $current->$part ?? null;
            } elseif (is_array($current)) {
                $current = $current[$part] ?? null;
            } else {
                return '';
            }

            if ($current === null) {
                return '';
            }
        }

        if ($current instanceof Collection) {
            return $current->implode(', ');
        }

        if ($current instanceof Model) {
            return (string) $current->getKey();
        }

        return (string) ($current ?? '');
    }

    private function specialValue(string $path): ?string
    {
        return match (strtolower($path)) {
            'uuid'  => (string) Str::uuid(),
            default => null,
        };
    }
}
