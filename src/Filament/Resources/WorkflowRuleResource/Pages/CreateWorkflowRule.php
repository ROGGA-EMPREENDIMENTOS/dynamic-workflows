<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Filament\Resources\WorkflowRuleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Rogga\DynamicWorkflows\Filament\Resources\WorkflowRuleResource;

class CreateWorkflowRule extends CreateRecord
{
    protected static string $resource = WorkflowRuleResource::class;
}
