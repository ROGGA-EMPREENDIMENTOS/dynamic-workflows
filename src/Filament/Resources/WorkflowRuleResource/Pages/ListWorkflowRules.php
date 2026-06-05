<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Filament\Resources\WorkflowRuleResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Rogga\DynamicWorkflows\Filament\Resources\WorkflowRuleResource;

class ListWorkflowRules extends ListRecords
{
    protected static string $resource = WorkflowRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
