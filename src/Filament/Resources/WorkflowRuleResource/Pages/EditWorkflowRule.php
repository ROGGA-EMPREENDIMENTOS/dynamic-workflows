<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Filament\Resources\WorkflowRuleResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Rogga\DynamicWorkflows\Filament\Resources\WorkflowRuleResource;

class EditWorkflowRule extends EditRecord
{
    protected static string $resource = WorkflowRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
