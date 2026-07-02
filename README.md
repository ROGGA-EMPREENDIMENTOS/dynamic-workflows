# Dynamic Workflows

Motor de regras de negócio para Laravel. Permite configurar, via interface gráfica, ações automáticas disparadas nos eventos do ciclo de vida de models Eloquent (`created`, `updated`, `deleted`), sem necessidade de código.

---

## Requisitos

| Dependência | Versão mínima |
|---|---|
| PHP | 8.2 |
| Laravel | 11.0 |
| Livewire | 3.0 |
| Filament (actions, forms, tables, support) | 3.0 ou 5.0 |

---

## Instalação em produção

### 1. Adicionar o repositório no `composer.json` do projeto

Como o pacote é privado (hospedado no GitHub da organização), adicione o repositório VCS:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:ROGGA-EMPREENDIMENTOS/dynamic-workflows.git"
    }
]
```

> **Pré-requisito:** a chave SSH do servidor de produção deve ter acesso de leitura ao repositório.
> No GitHub: **Settings → Deploy keys → Add deploy key** (marque apenas leitura).

### 2. Instalar o pacote

```bash
composer require rogga/dynamic-workflows:^1.0
```

### 3. Publicar a configuração

```bash
php artisan vendor:publish --tag=dynamic-workflows-config
```

### 4. Rodar as migrations

```bash
php artisan migrate
```

### 5. Adicionar a Trait nos models

```php
use Rogga\DynamicWorkflows\Traits\HasDynamicWorkflows;

class Order extends Model
{
    use HasDynamicWorkflows;
}
```

### 6. Acessar a interface

A rota é registrada automaticamente com autenticação obrigatória:

```
https://seu-dominio.com/dynamic-workflows
```

---

## Instalação em desenvolvimento (local)

Para desenvolver ou testar com symlink:

```json
"repositories": [
    {
        "type": "path",
        "url": "../DynamicWorkFlows"
    }
]
```

```bash
composer require rogga/dynamic-workflows @dev
```

---

## Configuração

### Interface gráfica (recomendado)

A partir da v1.2.0 todas as configurações de canais podem ser gerenciadas diretamente pela interface, sem necessidade de variáveis de ambiente. Na tela de workflows, clique no botão **⚙ Configurações** no canto superior direito da tabela.

O painel é organizado por grupo de ação. Para cada canal:

1. Ative ou desative o canal pelo toggle
2. Ao ativar, os campos de configuração aparecem logo abaixo
3. Salve — as configurações ficam armazenadas no banco de dados

Canais configuráveis pela interface:

| Canal | Campos configuráveis |
|---|---|
| **E-mail** | Ativar/desativar (credenciais via `.env` do Laravel) |
| **WhatsApp** | URL da API, Token, campo de telefone do usuário |
| **SMS** | URL da API, Chave da API, Remetente padrão, campo de telefone |
| **Webhook** | Ativar/desativar |
| **Alterar Campo** | Ativar/desativar |

> As credenciais salvas pela interface têm prioridade sobre as variáveis de ambiente. Os canais desativados não aparecem no formulário de cadastro de regras.

---

### Arquivo de configuração

```php
// config/dynamic-workflows.php

return [

    /*
    |--------------------------------------------------------------------------
    | Namespace base dos models
    |--------------------------------------------------------------------------
    | Permite digitar apenas "Order" no formulário em vez de "App\Models\Order".
    */
    'model_namespace' => 'App\\Models',

    /*
    |--------------------------------------------------------------------------
    | Rota da interface
    |--------------------------------------------------------------------------
    | Em produção mantenha sempre o middleware 'auth'.
    */
    'route' => [
        'prefix'     => 'dynamic-workflows',
        'middleware' => ['web', 'auth'],
        'name'       => 'dynamic-workflows.index',
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp API — fallback para variáveis de ambiente
    |--------------------------------------------------------------------------
    | Caso não configurado pela interface, usa os valores abaixo.
    */
    'whatsapp' => [
        'api_url'          => env('WHATSAPP_API_URL'),
        'api_token'        => env('WHATSAPP_API_TOKEN'),
        'user_phone_field' => 'phone',
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS — fallback para variáveis de ambiente
    |--------------------------------------------------------------------------
    | Caso não configurado pela interface, usa os valores abaixo.
    */
    'sms' => [
        'api_url'          => env('SMS_API_URL', 'https://sms.comtele.com.br/api/v2/send'),
        'api_key'          => env('SMS_API_KEY'),
        'sender'           => env('SMS_SENDER'),
        'user_phone_field' => env('SMS_USER_PHONE_FIELD', 'phone'),
    ],

];
```

### Variáveis de ambiente (opcional — fallback)

As variáveis abaixo são usadas apenas quando as configurações não foram salvas pela interface gráfica.

```env
# E-mail (obrigatório — gerenciado pelo Laravel)
MAIL_MAILER=smtp
MAIL_HOST=smtp.exemplo.com
MAIL_PORT=587
MAIL_USERNAME=usuario
MAIL_PASSWORD=senha
MAIL_FROM_ADDRESS=noreply@empresa.com
MAIL_FROM_NAME="${APP_NAME}"

# WhatsApp (opcional se configurado pela interface)
WHATSAPP_API_URL=https://api.z-api.io/instances/ID/token/TOKEN/send-text
WHATSAPP_API_TOKEN=seu_token

# SMS — Comtele (opcional se configurado pela interface)
SMS_API_KEY=sua_chave_comtele
SMS_SENDER=NomeApp        # máx. 11 caracteres, exibido como remetente no celular
# SMS_API_URL=            # opcional — sobrescreve o endpoint padrão da Comtele
# SMS_USER_PHONE_FIELD=   # opcional — campo de telefone no model User (padrão: phone)
```

---

## Como funciona

```
Model event (created / updated / deleted)
    └── HasDynamicWorkflows → processWorkflows()
            └── Busca WorkflowRules ativas para aquele model + evento
                    └── Avalia as condições (lógica AND)
                            └── Executa cada ação em sequência
```

### Condições

As condições são combinadas com lógica **AND** — todas precisam passar para as ações dispararem.

| Operador | Significado | Usa valor? |
|---|---|---|
| `=` | Igual a | Sim |
| `!=` | Diferente de | Sim |
| `>` | Maior que | Sim |
| `<` | Menor que | Sim |
| `>=` | Maior ou igual | Sim |
| `<=` | Menor ou igual | Sim |
| `like` | Contém (substring) | Sim |
| `is_empty` | Está vazio (nulo ou string vazia) | Não |
| `is_not_empty` | Está preenchido | Não |

Para os operadores que **não usam valor**, o campo "Valor" é ocultado e deixa de ser obrigatório.

#### Operadores exclusivos do evento *Atualizado*

Comparam o valor **anterior à alteração** e só aparecem quando o evento da regra é `updated`:

| Operador | Significado | Usa valor? |
|---|---|---|
| `changed` | O campo foi alterado (independente do valor) | Não |
| `changed_from` | O valor **anterior** era igual a X | Sim |
| `changed_to` | O campo foi alterado **e** o novo valor é X | Sim |

> Exemplo — disparar só quando `status` mudou de `pendente` para `aprovado`: uma condição `changed_from = pendente` + outra `changed_to = aprovado`.

---

## Ações disponíveis

### `send_email` — Enviar E-mail

| Campo | Descrição |
|---|---|
| Destinatário | E-mail direto, usuário específico ou criador do registro |
| Assunto | Suporta variáveis `{{campo}}` |
| Corpo | Suporta variáveis `{{campo}}` |

---

### `send_whatsapp` — Enviar WhatsApp

| Campo | Descrição |
|---|---|
| Destinatário | Número direto, usuário específico ou criador do registro |
| Mensagem | Suporta variáveis `{{campo}}` |

---

### `send_sms` — Enviar SMS

Integração nativa com a **Comtele** (SMS Marketing / transacional). A URL da API é configurável para outros provedores com payload compatível.

| Campo | Descrição |
|---|---|
| Destinatário | Número direto, usuário específico ou criador do registro |
| Remetente | Identificação exibida no celular — máx. 11 caracteres. Default: `APP_NAME` |
| Mensagem | Suporta variáveis `{{campo}}`. Limitada a **160 caracteres** |

**Pré-requisitos:**

```env
SMS_API_KEY=sua_chave_comtele
SMS_SENDER=NomeApp
```

O número do destinatário deve estar no formato `5511999999999` (DDI + DDD + número, sem espaços ou símbolos).

---

### `call_webhook` — Chamar Webhook

| Campo | Descrição |
|---|---|
| URL | Endpoint. Suporta variáveis `{{campo}}` |
| Método | `POST`, `GET` ou `PUT` |

Payload enviado automaticamente:

```json
{
    "model": "orders",
    "model_id": 42,
    "attributes": { "id": 42, "status": "aprovado" }
}
```

---

### `call_rest_api` — Chamar API REST

Realiza chamadas HTTP para APIs REST com suporte completo a autenticação e corpo dinâmico.

| Campo | Descrição |
|---|---|
| URL | Endpoint. Suporta variáveis `{{campo}}` |
| Método | `POST`, `PUT` ou `PATCH` |
| Content-Type | `application/json` ou `application/x-www-form-urlencoded` |
| Autenticação | Nenhuma, Bearer Token ou Basic Auth |
| Corpo (JSON) | Template JSON. Suporta variáveis `{{campo}}`. Ex: `{"status":"{{status}}"}` |
| Headers adicionais | Um por linha no formato `Chave: Valor`. Suporta variáveis `{{campo}}` |

---

### `update_field` — Alterar Campo

Atualiza um campo do model via query builder, sem disparar eventos Eloquent (sem risco de loop infinito).

| Campo | Descrição |
|---|---|
| Campo | Selecionado da tabela do model |
| Novo valor | Suporta variáveis `{{campo}}`. Ex: `{{status}}`, `{{customer.name}}` |

---

## Logs e diagnóstico

Todas as ações registram sua execução no log padrão do Laravel com a tag `[DynamicWorkflows]`. Para acompanhar em tempo real:

```bash
tail -f storage/logs/laravel.log | grep DynamicWorkflows
```

| Situação | Nível | Contém |
|---|---|---|
| Ação executada com sucesso | `info` | model, id e (nas ações HTTP) status + corpo da resposta |
| Resposta HTTP de erro (4xx/5xx) | `warning` | status + corpo da resposta — a ação **não** falha o job |
| Configuração incompleta (URL, destinatário, chave, etc.) | `warning` | quais campos faltaram |
| Corpo JSON inválido na `call_rest_api` | `warning` | JSON resolvido + mensagem do erro |
| Falha de conexão / transporte | `error` | mensagem do erro — a exceção é **re-lançada** |

> Falhas de conexão são re-lançadas de propósito: o job cai em `failed_jobs` e é re-tentado (`$tries = 3`). Já uma resposta HTTP de erro (a API respondeu) apenas gera `warning`, para você inspecionar o corpo sem entupir a fila de retentativas.

> **Filas:** as ações rodam via `ProcessWorkflowActionsJob` (assíncrono). Se `QUEUE_CONNECTION` não for `sync`, mantenha um `php artisan queue:work` ativo — caso contrário as ações não executam.

---

## Variáveis dinâmicas

Use `{{variavel}}` em qualquer campo de texto das ações:

| Sintaxe | Resultado |
|---|---|
| `{{id}}` | Atributo direto do model |
| `{{customer.name}}` | Campo de relação BelongsTo |
| `{{address.city}}` | Relação aninhada |
| `{{items.name}}` | HasMany — valores unidos por vírgula |

**Exemplos:**

```
Assunto:  Pedido #{{id}} — {{customer.name}}
Mensagem: Olá {{customer.name}}, seu pedido está {{status}}.
URL:      https://api.exemplo.com/orders/{{id}}/notify
```

---

## Destinatário dinâmico (e-mail, WhatsApp e SMS)

| Opção | Comportamento |
|---|---|
| **Direto** | Endereço/número fixo digitado na regra |
| **Usuário específico** | Seleciona um registro da tabela `users` |
| **Criador do registro** | Lê o campo configurado (ex: `created_by`), busca o usuário e usa seu e-mail/telefone |

O campo de telefone buscado nos usuários é configurável por canal:

```env
WHATSAPP_USER_PHONE_FIELD=celular   # padrão: phone
SMS_USER_PHONE_FIELD=celular        # padrão: phone
```

---

## Campos disponíveis no formulário

Por padrão, todos os campos da tabela são carregados via `Schema::getColumnListing()` automaticamente.

Para personalizar com labels legíveis:

```php
public function getWorkflowFields(): array
{
    return [
        'status'         => 'Status',
        'total'          => 'Valor Total',
        'payment_method' => 'Pagamento',
    ];
}
```

---

## Adicionando ações customizadas

**1. Criar a classe:**

```php
use Rogga\DynamicWorkflows\Contracts\ActionHandler;
use Illuminate\Database\Eloquent\Model;

class NotificarSlackAction implements ActionHandler
{
    public function handle(Model $model, array $config): void
    {
        Http::post($config['slack_webhook'], ['text' => $config['slack_message']]);
    }

    public function getLabel(): string
    {
        return 'Notificar Slack';
    }
}
```

**2. Registrar no `AppServiceProvider`:**

```php
use Rogga\DynamicWorkflows\DynamicWorkflows;

public function boot(): void
{
    DynamicWorkflows::registerAction('minha_acao', MinhaAcaoCustomizada::class);
}
```

---

## Recomendações para produção

### Filas (queues)

Para não bloquear a requisição ao disparar e-mails ou webhooks, implemente handlers que despacham Jobs:

```php
class EnviarEmailAction implements ActionHandler
{
    public function handle(Model $model, array $config): void
    {
        ProcessWorkflowEmailJob::dispatch($model->getKey(), get_class($model), $config);
    }
}
```

### Segurança

- Mantenha sempre `'auth'` no middleware da rota
- Use **Deploy Keys** no GitHub com permissão somente leitura para o servidor de produção
- Revise as ações cadastradas periodicamente, especialmente webhooks com URLs externas

### Cache

Após qualquer atualização do pacote em produção:

```bash
php artisan optimize:clear
composer dump-autoload
```

---

## Integração com Filament Panel

Se o projeto usar Filament Panel, registre o plugin no `PanelProvider`:

```php
use Rogga\DynamicWorkflows\DynamicWorkflowsPlugin;

->plugins([
    DynamicWorkflowsPlugin::make(),
])
```

---

## Publicando assets

```bash
php artisan vendor:publish --tag=dynamic-workflows-config
php artisan vendor:publish --tag=dynamic-workflows-migrations
php artisan vendor:publish --tag=dynamic-workflows-views
```

---

## Estrutura do pacote

```
src/
├── Actions/
│   ├── CallWebhookAction.php
│   ├── SendEmailAction.php
│   ├── SendSmsAction.php
│   ├── SendWhatsAppAction.php
│   └── UpdateFieldAction.php
├── Contracts/
│   └── ActionHandler.php          ← interface para ações customizadas
├── Filament/Resources/            ← Resource para Filament Panel
├── Livewire/
│   ├── WorkflowRuleList.php       ← tabela com CRUD modal + botão de configurações
│   └── WorkflowRuleForm.php       ← formulário standalone
├── Mail/WorkflowMail.php
├── Models/
│   ├── WorkflowRule.php
│   └── WorkflowSettings.php      ← configurações persistidas no banco
├── Traits/HasDynamicWorkflows.php ← adicionar nos models do projeto
├── ActionRegistry.php
├── DynamicWorkflows.php           ← API estática pública
├── DynamicWorkflowsPlugin.php
├── DynamicWorkflowsServiceProvider.php
└── VariableResolver.php
```

---

## Versionamento

| Versão | Descrição |
|---|---|
| `1.4.0` | Condições com valor opcional e operadores `is_empty` / `is_not_empty`; operadores `changed`, `changed_from` e `changed_to` no evento *Atualizado*, que comparam com o valor anterior à alteração; logs em todas as actions (tag `[DynamicWorkflows]`) — status/corpo das respostas HTTP, avisos de configuração incompleta e re-lançamento de falhas de conexão para `failed_jobs` |
| `1.3.0` | Nova ação `call_rest_api` (POST/PUT/PATCH, Bearer/Basic Auth, headers e corpo dinâmico); execução de ações via Job assíncrono (`ProcessWorkflowActionsJob`); suporte a variáveis `{{campo}}` no campo "Novo valor" da ação `update_field` |
| `1.2.0` | Cadastro de configurações via interface (⚙), ativar/desativar ações por canal, credenciais de WhatsApp e SMS persistidas no banco |
| `1.1.0` | Ação de envio de SMS (Comtele), e-mail com Markdown e layout do host, botão "Salvar" no formulário |
| `1.0.1` | Produção: rota protegida por auth, README revisado |
| `1.0.0` | Release inicial |

---

## Licença

MIT © Rogga Empreendimentos
