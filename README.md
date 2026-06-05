# Dynamic Workflows

Motor de regras de negócio para Laravel. Permite configurar, via interface gráfica, ações automáticas que são disparadas nos eventos do ciclo de vida de models Eloquent (`created`, `updated`, `deleted`), sem necessidade de código.

---

## Requisitos

| Dependência | Versão |
|---|---|
| PHP | ^8.2 |
| Laravel | ^11.0 \| ^12.0 |
| Livewire | ^3.0 \| ^4.0 |
| Filament Actions | ^3.0 \| ^5.0 |
| Filament Forms | ^3.0 \| ^5.0 |
| Filament Tables | ^3.0 \| ^5.0 |
| Filament Support | ^3.0 \| ^5.0 |

---

## Instalação

### 1. Configurar repositório local (desenvolvimento)

No `composer.json` do projeto host, adicione o repositório path:

```json
"repositories": [
    {
        "type": "path",
        "url": "../DynamicWorkFlows"
    }
]
```

### 2. Instalar o pacote

```bash
composer require rogga/dynamic-workflows @dev
```

> **Nota:** O `@dev` é necessário pois o pacote ainda não possui versão tagueada. Em produção, crie uma tag (`git tag v1.0.0`) e remova o `@dev`.

### 3. Rodar as migrations

```bash
php artisan migrate
```

Cria a tabela `workflow_rules` com os campos:

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | bigint | Chave primária |
| `name` | string | Nome da regra |
| `model_class` | string | Classe completa do model |
| `event` | string | `created`, `updated` ou `deleted` |
| `conditions` | json | Condições para disparo |
| `actions` | json | Ações a executar |
| `is_active` | boolean | Ativa/inativa |
| `created_by` | bigint | Usuário que criou |
| `updated_by` | bigint | Usuário que editou por último |
| `timestamps` | — | `created_at`, `updated_at` |

### 4. Adicionar a Trait ao model

Em cada model que deve disparar workflows:

```php
use Rogga\DynamicWorkflows\Traits\HasDynamicWorkflows;

class Order extends Model
{
    use HasDynamicWorkflows;
}
```

### 5. Adicionar a view

Crie uma rota e view no projeto host. O pacote já registra automaticamente a rota `/dynamic-workflows`. Caso queira integrar ao seu layout:

```blade
@livewire('dynamic-workflows.workflow-rule-list')
```

Certifique-se de que o layout inclua os assets do Filament e Livewire:

```blade
@filamentStyles
@livewireStyles
{{-- conteúdo --}}
@livewireScripts
@filamentScripts
```

---

## Acesso à interface

Após a instalação, a interface estará disponível em:

```
http://seu-projeto.test/dynamic-workflows
```

A URL pode ser personalizada publicando o arquivo de configuração (ver seção [Configuração](#configuração)).

---

## Configuração

Publique o arquivo de configuração:

```bash
php artisan vendor:publish --tag=dynamic-workflows-config
```

```php
// config/dynamic-workflows.php

return [
    // Namespace base dos models do projeto
    // Permite digitar apenas "Order" no formulário em vez de "App\Models\Order"
    'model_namespace' => 'App\\Models',

    // Rota da interface de gerenciamento
    'route' => [
        'prefix'     => 'dynamic-workflows',   // URL: /dynamic-workflows
        'middleware' => ['web', 'auth'],         // Adicione 'auth' para proteger
        'name'       => 'dynamic-workflows.index',
    ],

    // Credenciais para o handler de WhatsApp
    'whatsapp' => [
        'api_url'          => env('WHATSAPP_API_URL', ''),
        'api_token'        => env('WHATSAPP_API_TOKEN', ''),
        'user_phone_field' => 'phone', // Campo de telefone no model User
    ],
];
```

---

## Como funciona

### Fluxo de execução

```
Model event (created/updated/deleted)
    └── HasDynamicWorkflows::processWorkflows()
            └── Busca WorkflowRules ativas para o model + evento
                    └── Avalia as condições da regra
                            └── Se aprovadas, executa cada ação em sequência
```

### Condições

Cada regra pode ter zero ou mais condições. Todas devem ser verdadeiras para as ações serem executadas (lógica AND).

| Operador | Significado |
|---|---|
| `=` | Igual a |
| `!=` | Diferente de |
| `>` | Maior que |
| `<` | Menor que |
| `>=` | Maior ou igual |
| `<=` | Menor ou igual |
| `like` | Contém (substring) |

**Exemplo:** Disparar apenas quando `status` for igual a `aprovado`.

---

## Ações disponíveis

### Enviar E-mail (`send_email`)

Envia um e-mail via `Mail` do Laravel.

| Campo | Descrição |
|---|---|
| Destinatário | E-mail direto, usuário específico ou criador do registro |
| Assunto | Suporta variáveis `{{campo}}` |
| Corpo | Suporta variáveis `{{campo}}` |

Requer configuração do driver de e-mail no `.env` do projeto:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.exemplo.com
MAIL_PORT=587
MAIL_USERNAME=usuario
MAIL_PASSWORD=senha
MAIL_FROM_ADDRESS=noreply@exemplo.com
```

---

### Enviar WhatsApp (`send_whatsapp`)

Envia mensagem via API HTTP compatível com Z-API, Evolution API e similares.

| Campo | Descrição |
|---|---|
| Destinatário | Número direto, usuário específico ou criador do registro |
| Mensagem | Suporta variáveis `{{campo}}` |

Configure no `.env`:

```env
WHATSAPP_API_URL=https://api.z-api.io/instances/ID/token/TOKEN/send-text
WHATSAPP_API_TOKEN=seu_token
```

---

### Chamar Webhook (`call_webhook`)

Dispara uma requisição HTTP para uma URL externa com os atributos do model.

| Campo | Descrição |
|---|---|
| URL | Endpoint. Suporta variáveis `{{campo}}` |
| Método | `POST`, `GET` ou `PUT` |

O payload enviado contém:

```json
{
    "model": "orders",
    "model_id": 42,
    "attributes": { "id": 42, "status": "aprovado", ... }
}
```

---

### Alterar Campo (`update_field`)

Atualiza um campo do próprio model que disparou o evento.

| Campo | Descrição |
|---|---|
| Campo | Selecionado dinamicamente da tabela do model |
| Novo Valor | Valor literal a ser salvo |

> A atualização usa query builder diretamente para evitar loop infinito de eventos.

---

## Variáveis dinâmicas

Em qualquer campo de texto das ações (assunto, corpo do e-mail, mensagem, URL), você pode usar variáveis que serão substituídas pelos valores do model no momento do disparo.

### Sintaxe

```
{{campo}}               → atributo direto do model
{{relacao.campo}}       → campo de uma relação BelongsTo
{{relacao.outra.campo}} → relação aninhada
```

### Exemplos

```
Olá {{customer.name}}, seu pedido #{{id}} está {{status}}.

Valor total: {{total}}
Endereço: {{address.street}}, {{address.city}}

https://api.exemplo.com/orders/{{id}}/notify
```

---

## Expondo campos do model na interface

Por padrão, o pacote lê as colunas da tabela via `Schema::getColumnListing()`. Para personalizar quais campos aparecem nos seletores de condição e "Alterar Campo", implemente `getWorkflowFields()` no model:

### Array simples (chave = label)

```php
public function getWorkflowFields(): array
{
    return ['status', 'total', 'payment_method', 'notes'];
}
```

### Array associativo (chave do banco => label legível)

```php
public function getWorkflowFields(): array
{
    return [
        'status'         => 'Status do Pedido',
        'total'          => 'Valor Total',
        'payment_method' => 'Método de Pagamento',
        'notes'          => 'Observações',
    ];
}
```

---

## Adicionando ações customizadas

### 1. Criar a classe da ação

```php
use Rogga\DynamicWorkflows\Contracts\ActionHandler;
use Illuminate\Database\Eloquent\Model;

class EnviarSmsAction implements ActionHandler
{
    public function handle(Model $model, array $config): void
    {
        $numero  = $config['sms_to']      ?? null;
        $mensagem = $config['sms_message'] ?? null;

        if (! $numero || ! $mensagem) {
            return;
        }

        // Integração com seu provider de SMS
        SmsService::send($numero, $mensagem);
    }

    public function getLabel(): string
    {
        return 'Enviar SMS';
    }
}
```

### 2. Registrar no AppServiceProvider

```php
use Rogga\DynamicWorkflows\DynamicWorkflows;

public function boot(): void
{
    DynamicWorkflows::registerAction('send_sms', EnviarSmsAction::class);
}
```

A nova opção aparece automaticamente no formulário de regras.

---

## Publicando assets

```bash
# Configuração
php artisan vendor:publish --tag=dynamic-workflows-config

# Migrations (para customizar)
php artisan vendor:publish --tag=dynamic-workflows-migrations

# Views (para customizar o layout)
php artisan vendor:publish --tag=dynamic-workflows-views
```

---

## Estrutura do pacote

```
src/
├── Actions/
│   ├── CallWebhookAction.php      # Handler: chamar webhook
│   ├── SendEmailAction.php        # Handler: enviar e-mail
│   ├── SendWhatsAppAction.php     # Handler: enviar WhatsApp
│   └── UpdateFieldAction.php      # Handler: alterar campo
├── Contracts/
│   └── ActionHandler.php          # Interface para ações customizadas
├── Filament/Resources/            # Resource para uso com Filament Panel
│   └── WorkflowRuleResource.php
├── Livewire/
│   ├── WorkflowRuleList.php       # Componente principal (tabela + CRUD modal)
│   └── WorkflowRuleForm.php       # Componente de formulário standalone
├── Mail/
│   └── WorkflowMail.php           # Mailable para o handler de e-mail
├── Models/
│   └── WorkflowRule.php           # Model da tabela workflow_rules
├── Traits/
│   └── HasDynamicWorkflows.php    # Trait para os models do projeto
├── ActionRegistry.php             # Registro de handlers de ação
├── DynamicWorkflows.php           # Classe de entrada (API estática)
├── DynamicWorkflowsPlugin.php     # Plugin para Filament Panel
├── DynamicWorkflowsServiceProvider.php
└── VariableResolver.php           # Interpolação de {{variáveis}}
```

---

## Licença

MIT
