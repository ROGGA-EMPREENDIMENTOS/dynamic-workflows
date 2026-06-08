<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Livewire\Component;
use Rogga\DynamicWorkflows\ActionRegistry;
use Rogga\DynamicWorkflows\Models\WorkflowRule;
use Rogga\DynamicWorkflows\Models\WorkflowSettings;

class WorkflowRuleList extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(WorkflowRule::query())
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('model_class')
                    ->label('Model')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('event')
                    ->label('Evento')
                    ->badge(),
                IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
                TextColumn::make('updater.name')
                    ->label('Alterado por')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Alterado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('model_class')
                    ->label('Filtrar por Model')
                    ->options(
                        fn () => WorkflowRule::query()
                            ->distinct()
                            ->orderBy('model_class')
                            ->pluck('model_class', 'model_class')
                            ->toArray()
                    )
                    ->placeholder('Todos os models'),
            ])
            ->headerActions([
                Action::make('settings')
                    ->label('Configurações')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('gray')
                    ->modalHeading('Configurações do Workflow')
                    ->modalSubmitActionLabel('Salvar')
                    ->modalWidth('2xl')
                    ->fillForm(fn (): array => WorkflowSettings::getFormData())
                    ->form(self::settingsFormSchema())
                    ->action(fn (array $data) => WorkflowSettings::store($data)),
                Action::make('create')
                    ->label('Nova Regra')
                    ->icon('heroicon-o-plus')
                    ->modalSubmitActionLabel('Salvar')
                    ->form(self::workflowFormSchema())
                    ->action(fn (array $data) => WorkflowRule::create($data)),
            ])
            ->actions([
                EditAction::make()
                    ->modalSubmitActionLabel('Salvar')
                    ->form(self::workflowFormSchema())
                    ->action(fn (WorkflowRule $record, array $data) => $record->update($data)),
                Action::make('duplicate')
                    ->label('Duplicar')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (WorkflowRule $record, Component $livewire) {
                        $copy = $record->replicate();
                        $copy->name = $record->name.' (cópia)';
                        $copy->save();

                        $livewire->mountTableAction('edit', (string) $copy->getKey());
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function workflowFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            TextInput::make('model_class')
                ->label('Model')
                ->required()
                ->placeholder('Order')
                ->helperText(fn () => 'Namespace aplicado automaticamente: '.config('dynamic-workflows.model_namespace', 'App\\Models'))
                ->formatStateUsing(fn (?string $state): ?string => $state ? class_basename($state) : null)
                ->dehydrateStateUsing(function (?string $state): ?string {
                    if (!$state) {
                        return null;
                    }
                    if (str_contains($state, '\\')) {
                        return $state;
                    }
                    $ns = rtrim(config('dynamic-workflows.model_namespace', 'App\\Models'), '\\');

                    return $ns.'\\'.$state;
                })
                ->live(debounce: 600)
                ->columnSpanFull(),

            Select::make('event')
                ->label('Evento')
                ->options([
                    'created' => 'Criado',
                    'updated' => 'Atualizado',
                    'deleted' => 'Deletado',
                ])
                ->required(),

            Toggle::make('is_active')
                ->label('Ativo')
                ->default(true),

            // ── Condições ──────────────────────────────────────────────────────
            Repeater::make('conditions')
                ->label('Condições')
                ->schema([
                    Select::make('field')
                        ->label('Campo')
                        ->options(fn (Get $get) => self::modelFields($get('../../model_class')))
                        ->searchable()
                        ->required(),
                    Select::make('operator')
                        ->label('Operador')
                        ->options([
                            '=' => 'Igual a',
                            '!=' => 'Diferente de',
                            '>' => 'Maior que',
                            '<' => 'Menor que',
                            '>=' => 'Maior ou igual',
                            '<=' => 'Menor ou igual',
                            'like' => 'Contém',
                        ])
                        ->required(),
                    TextInput::make('value')
                        ->label('Valor')
                        ->required(),
                ])
                ->columns(3)
                ->collapsible()
                ->columnSpanFull(),

            // ── Ações ──────────────────────────────────────────────────────────
            Repeater::make('actions')
                ->label('Ações')
                ->schema([
                    Select::make('type')
                        ->label('Tipo de Ação')
                        ->options(fn () => app(ActionRegistry::class)->enabledOptions())
                        ->required()
                        ->live()
                        ->columnSpanFull(),

                    // ── Enviar E-mail ──────────────────────────────────────────
                    Select::make('email_recipient_type')
                        ->label('Destinatário')
                        ->options([
                            'direct' => 'E-mail direto',
                            'user' => 'Usuário específico',
                            'creator' => 'Criador do registro',
                        ])
                        ->default('direct')
                        ->live()
                        ->visible(fn (Get $get) => $get('type') === 'send_email')
                        ->columnSpanFull(),

                    TextInput::make('email_to')
                        ->label('Para (e-mail)')
                        ->email()
                        ->visible(fn (Get $get) => $get('type') === 'send_email' && $get('email_recipient_type') === 'direct')
                        ->required(fn (Get $get) => $get('type') === 'send_email' && $get('email_recipient_type') === 'direct'),

                    Select::make('email_user_id')
                        ->label('Usuário')
                        ->options(fn () => self::userOptions())
                        ->searchable()
                        ->visible(fn (Get $get) => $get('type') === 'send_email' && $get('email_recipient_type') === 'user')
                        ->required(fn (Get $get) => $get('type') === 'send_email' && $get('email_recipient_type') === 'user'),

                    TextInput::make('email_creator_field')
                        ->label('Campo do criador no model (ex: created_by)')
                        ->default('created_by')
                        ->visible(fn (Get $get) => $get('type') === 'send_email' && $get('email_recipient_type') === 'creator')
                        ->required(fn (Get $get) => $get('type') === 'send_email' && $get('email_recipient_type') === 'creator'),

                    TextInput::make('email_subject')
                        ->label('Assunto')
                        ->helperText('Use {{campo}} ou {{relacao.campo}}. Ex: Pedido {{id}} de {{customer.name}}')
                        ->visible(fn (Get $get) => $get('type') === 'send_email')
                        ->required(fn (Get $get) => $get('type') === 'send_email'),

                    Textarea::make('email_body')
                        ->label('Corpo do e-mail')
                        ->helperText('Use {{campo}} ou {{relacao.campo}}. Ex: Olá {{customer.name}}, seu pedido {{id}} foi {{status}}.')
                        ->rows(4)
                        ->visible(fn (Get $get) => $get('type') === 'send_email')
                        ->required(fn (Get $get) => $get('type') === 'send_email')
                        ->columnSpanFull(),

                    // ── Enviar WhatsApp ────────────────────────────────────────
                    Select::make('whatsapp_recipient_type')
                        ->label('Destinatário')
                        ->options([
                            'direct' => 'Número direto',
                            'user' => 'Usuário específico',
                            'creator' => 'Criador do registro',
                        ])
                        ->default('direct')
                        ->live()
                        ->visible(fn (Get $get) => $get('type') === 'send_whatsapp')
                        ->columnSpanFull(),

                    TextInput::make('whatsapp_to')
                        ->label('Número (ex: 5511999999999)')
                        ->visible(fn (Get $get) => $get('type') === 'send_whatsapp' && $get('whatsapp_recipient_type') === 'direct')
                        ->required(fn (Get $get) => $get('type') === 'send_whatsapp' && $get('whatsapp_recipient_type') === 'direct'),

                    Select::make('whatsapp_user_id')
                        ->label('Usuário')
                        ->options(fn () => self::userOptions())
                        ->searchable()
                        ->visible(fn (Get $get) => $get('type') === 'send_whatsapp' && $get('whatsapp_recipient_type') === 'user')
                        ->required(fn (Get $get) => $get('type') === 'send_whatsapp' && $get('whatsapp_recipient_type') === 'user'),

                    TextInput::make('whatsapp_creator_field')
                        ->label('Campo do criador no model (ex: created_by)')
                        ->default('created_by')
                        ->visible(fn (Get $get) => $get('type') === 'send_whatsapp' && $get('whatsapp_recipient_type') === 'creator')
                        ->required(fn (Get $get) => $get('type') === 'send_whatsapp' && $get('whatsapp_recipient_type') === 'creator'),

                    Textarea::make('whatsapp_message')
                        ->label('Mensagem')
                        ->helperText('Use {{campo}} ou {{relacao.campo}}. Ex: Olá {{customer.name}}, seu pedido {{id}} está {{status}}.')
                        ->rows(3)
                        ->visible(fn (Get $get) => $get('type') === 'send_whatsapp')
                        ->required(fn (Get $get) => $get('type') === 'send_whatsapp')
                        ->columnSpanFull(),

                    // ── Enviar SMS ─────────────────────────────────────────────
                    Select::make('sms_recipient_type')
                        ->label('Destinatário')
                        ->options([
                            'direct' => 'Número direto',
                            'user' => 'Usuário específico',
                            'creator' => 'Criador do registro',
                        ])
                        ->default('direct')
                        ->live()
                        ->visible(fn (Get $get) => $get('type') === 'send_sms')
                        ->columnSpanFull(),

                    TextInput::make('sms_to')
                        ->label('Número (ex: 5511999999999)')
                        ->visible(fn (Get $get) => $get('type') === 'send_sms' && $get('sms_recipient_type') === 'direct')
                        ->required(fn (Get $get) => $get('type') === 'send_sms' && $get('sms_recipient_type') === 'direct'),

                    Select::make('sms_user_id')
                        ->label('Usuário')
                        ->options(fn () => self::userOptions())
                        ->searchable()
                        ->visible(fn (Get $get) => $get('type') === 'send_sms' && $get('sms_recipient_type') === 'user')
                        ->required(fn (Get $get) => $get('type') === 'send_sms' && $get('sms_recipient_type') === 'user'),

                    TextInput::make('sms_creator_field')
                        ->label('Campo do criador no model (ex: created_by)')
                        ->default('created_by')
                        ->visible(fn (Get $get) => $get('type') === 'send_sms' && $get('sms_recipient_type') === 'creator')
                        ->required(fn (Get $get) => $get('type') === 'send_sms' && $get('sms_recipient_type') === 'creator'),

                    TextInput::make('sms_sender')
                        ->label('Remetente (identificação)')
                        ->default(fn () => config('app.name', 'Sistema'))
                        ->maxLength(11)
                        ->visible(fn (Get $get) => $get('type') === 'send_sms')
                        ->required(fn (Get $get) => $get('type') === 'send_sms'),

                    Textarea::make('sms_message')
                        ->label('Mensagem')
                        ->helperText('Use {{campo}} ou {{relacao.campo}}. Ex: Olá {{customer.name}}, seu pedido {{id}} está {{status}}.')
                        ->rows(3)
                        ->maxLength(160)
                        ->visible(fn (Get $get) => $get('type') === 'send_sms')
                        ->required(fn (Get $get) => $get('type') === 'send_sms')
                        ->columnSpanFull(),

                    // ── Chamar Webhook ─────────────────────────────────────────
                    TextInput::make('webhook_url')
                        ->label('URL do Webhook')
                        ->helperText('Variáveis permitidas na URL. Ex: https://api.exemplo.com/orders/{{id}}')
                        ->visible(fn (Get $get) => $get('type') === 'call_webhook')
                        ->required(fn (Get $get) => $get('type') === 'call_webhook')
                        ->columnSpanFull(),

                    Select::make('webhook_method')
                        ->label('Método HTTP')
                        ->options(['POST' => 'POST', 'GET' => 'GET', 'PUT' => 'PUT'])
                        ->default('POST')
                        ->visible(fn (Get $get) => $get('type') === 'call_webhook'),

                    // ── Alterar Campo ──────────────────────────────────────────
                    Select::make('field_name')
                        ->label('Campo do Model')
                        ->options(fn (Get $get) => self::modelFields($get('../../model_class')))
                        ->searchable()
                        ->visible(fn (Get $get) => $get('type') === 'update_field')
                        ->required(fn (Get $get) => $get('type') === 'update_field'),

                    TextInput::make('field_value')
                        ->label('Novo Valor')
                        ->visible(fn (Get $get) => $get('type') === 'update_field')
                        ->required(fn (Get $get) => $get('type') === 'update_field'),
                ])
                ->columns(2)
                ->collapsible()
                ->columnSpanFull(),
        ];
    }

    protected static function modelFields(?string $modelClass): array
    {
        if (!$modelClass) {
            return [];
        }

        if (!str_contains($modelClass, '\\')) {
            $ns = rtrim(config('dynamic-workflows.model_namespace', 'App\\Models'), '\\');
            $modelClass = $ns.'\\'.$modelClass;
        }

        if (!class_exists($modelClass)) {
            return [];
        }

        try {
            $model = new $modelClass();

            if (method_exists($model, 'getWorkflowFields')) {
                $fields = $model->getWorkflowFields();

                if (!empty($fields)) {
                    return array_is_list($fields)
                        ? array_combine($fields, $fields)
                        : $fields;
                }
            }

            // Fallback: lê colunas direto do schema do banco
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing($model->getTable());

            return array_combine($columns, $columns);
        } catch (\Throwable) {
            return [];
        }
    }

    protected static function userOptions(): array
    {
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        if (!class_exists($userModel)) {
            return [];
        }

        try {
            return $userModel::query()->pluck('name', 'id')->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    public static function settingsFormSchema(): array
    {
        return [
            Section::make('E-mail')
                ->schema([
                    Toggle::make('email_enabled')
                        ->label('Ativar envio de e-mail')
                        ->default(true)
                        ->live(),
                ]),

            Section::make('WhatsApp')
                ->schema([
                    Toggle::make('whatsapp_enabled')
                        ->label('Ativar envio de WhatsApp')
                        ->default(true)
                        ->live(),
                    TextInput::make('whatsapp_api_url')
                        ->label('URL da API')
                        ->placeholder('https://api.z-api.io/...')
                        ->visible(fn (Get $get): bool => (bool) $get('whatsapp_enabled'))
                        ->columnSpanFull(),
                    TextInput::make('whatsapp_api_token')
                        ->label('Token da API')
                        ->password()
                        ->revealable()
                        ->visible(fn (Get $get): bool => (bool) $get('whatsapp_enabled'))
                        ->columnSpanFull(),
                    TextInput::make('whatsapp_user_phone_field')
                        ->label('Campo de telefone do usuário')
                        ->placeholder('phone')
                        ->helperText('Nome do campo na tabela de usuários que contém o número de telefone')
                        ->visible(fn (Get $get): bool => (bool) $get('whatsapp_enabled')),
                ])
                ->columns(2),

            Section::make('SMS')
                ->schema([
                    Toggle::make('sms_enabled')
                        ->label('Ativar envio de SMS')
                        ->default(true)
                        ->live(),
                    TextInput::make('sms_api_url')
                        ->label('URL da API')
                        ->placeholder('https://sms.comtele.com.br/api/v2/send')
                        ->visible(fn (Get $get): bool => (bool) $get('sms_enabled'))
                        ->columnSpanFull(),
                    TextInput::make('sms_api_key')
                        ->label('Chave da API')
                        ->password()
                        ->revealable()
                        ->visible(fn (Get $get): bool => (bool) $get('sms_enabled')),
                    TextInput::make('sms_sender')
                        ->label('Remetente padrão')
                        ->maxLength(11)
                        ->placeholder(config('app.name', 'Sistema'))
                        ->visible(fn (Get $get): bool => (bool) $get('sms_enabled')),
                    TextInput::make('sms_user_phone_field')
                        ->label('Campo de telefone do usuário')
                        ->placeholder('phone')
                        ->helperText('Nome do campo na tabela de usuários que contém o número de telefone')
                        ->visible(fn (Get $get): bool => (bool) $get('sms_enabled')),
                ])
                ->columns(2),

            Section::make('Webhook')
                ->schema([
                    Toggle::make('webhook_enabled')
                        ->label('Ativar chamadas de webhook')
                        ->default(true),
                ]),

            Section::make('Alterar Campo')
                ->schema([
                    Toggle::make('update_field_enabled')
                        ->label('Ativar alteração de campo')
                        ->default(true),
                ]),
        ];
    }

    public function render(): \Illuminate\View\View
    {
        return view('dynamic-workflows::livewire.workflow-rule-list');
    }
}
