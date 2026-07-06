<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows;

use Rogga\DynamicWorkflows\Contracts\ActionHandler;

class DynamicWorkflows
{
    /**
     * Nome do pacote no Composer, usado para resolver a versão instalada.
     */
    public const PACKAGE = 'rogga/dynamic-workflows';

    public static function registerAction(string $key, string|ActionHandler $handler): void
    {
        $instance = is_string($handler) ? app($handler) : $handler;

        app(ActionRegistry::class)->register($key, $instance);
    }

    /**
     * Versão instalada do pacote (ex.: "1.3.0" ou "dev-main"). Retorna "dev"
     * quando não é possível resolver (ex.: rodando fora do contexto Composer).
     */
    public static function version(): string
    {
        if (class_exists(\Composer\InstalledVersions::class)
            && \Composer\InstalledVersions::isInstalled(self::PACKAGE)) {
            return \Composer\InstalledVersions::getPrettyVersion(self::PACKAGE) ?? 'dev';
        }

        return 'dev';
    }
}
