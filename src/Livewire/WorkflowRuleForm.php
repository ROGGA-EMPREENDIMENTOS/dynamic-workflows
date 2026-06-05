<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Livewire;

use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Component;
use Rogga\DynamicWorkflows\Models\WorkflowRule;

class WorkflowRuleForm extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public ?int $ruleId = null;

    public function mount(?int $ruleId = null): void
    {
        $this->ruleId = $ruleId;

        $rule = $ruleId
            ? WorkflowRule::findOrFail($ruleId)
            : new WorkflowRule();

        $this->form->fill($rule->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(WorkflowRuleList::workflowFormSchema())
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if ($this->ruleId) {
            WorkflowRule::findOrFail($this->ruleId)->update($data);
            $this->dispatch('rule-updated', id: $this->ruleId);
        } else {
            $rule = WorkflowRule::create($data);
            $this->dispatch('rule-created', id: $rule->id);
        }
    }

    public function delete(): void
    {
        if ($this->ruleId) {
            WorkflowRule::findOrFail($this->ruleId)->delete();
            $this->dispatch('rule-deleted', id: $this->ruleId);
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('dynamic-workflows::livewire.workflow-rule-form');
    }
}
