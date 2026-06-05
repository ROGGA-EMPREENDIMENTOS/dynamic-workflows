<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Filament\Resources;

use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Rogga\DynamicWorkflows\Filament\Resources\WorkflowRuleResource\Pages;
use Rogga\DynamicWorkflows\Livewire\WorkflowRuleList;
use Rogga\DynamicWorkflows\Models\WorkflowRule;

class WorkflowRuleResource extends Resource
{
    protected static ?string $model = WorkflowRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Workflow Rules';

    protected static ?string $navigationGroup = 'Workflows';

    public static function form(Form $form): Form
    {
        return $form->schema(WorkflowRuleList::workflowFormSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('model_class')->label('Model')->searchable(),
                TextColumn::make('event')->label('Evento')->badge(),
                IconColumn::make('is_active')->label('Ativo')->boolean(),
                TextColumn::make('created_at')->label('Criado em')->dateTime()->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWorkflowRules::route('/'),
            'create' => Pages\CreateWorkflowRule::route('/create'),
            'edit'   => Pages\EditWorkflowRule::route('/{record}/edit'),
        ];
    }
}
