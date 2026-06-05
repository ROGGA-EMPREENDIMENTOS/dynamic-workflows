Atue como um Engenheiro de Software Sênior especialista em Laravel, Livewire e desenvolvimento de pacotes para o Filament v3.

Meu objetivo é criar um pacote Laravel instalável via Composer chamado "Dynamic Workflows". Este pacote funcionará como um Motor de Regras de Negócio (Rule Engine) onde o usuário final pode configurar ações que ocorrem no ciclo de vida de um model Eloquent (created, updated, deleted) através de um painel feito em Filament.

Por favor, gere o código e a estrutura de diretórios inicial para este pacote. Considere as seguintes especificações:

1. Estrutura do Pacote e Configuração:

Nome do vendor/package: rogga/dynamic-workflows

Namespace principal: Rogga\DynamicWorkflows\

Gere o arquivo composer.json básico exigindo Laravel 11+ e Filament v3+, configurando o Autoload PSR-4 e o Extra (Laravel Provider discovery).

Gere o DynamicWorkflowsServiceProvider.php registrando as migrations, views (se necessário) e o arquivo de configuração.

2. Banco de Dados:

Gere a migration para a tabela workflow_rules.

Campos necessários: id, name (string), model_class (string), event (string), conditions (json), actions (json), is_active (boolean), timestamps.

Gere o Model WorkflowRule correspondente com os devidos casts para array/json.

3. A Integração com os Models do Cliente:

Crie uma Trait chamada HasDynamicWorkflows.

Esta Trait deve usar os eventos estáticos de boot do Eloquent (created, updated, deleted) para interceptar as ações.

A Trait deve ter métodos stub para: getWorkflowName(): string e getWorkflowFields(): array (para expor os campos do model para a UI).

4. O Plugin e a Interface Filament (v3):

Crie a classe DynamicWorkflowsPlugin implementando \Filament\Contracts\Plugin para registrar o painel de administração.

Crie um Filament Resource chamado WorkflowRuleResource.

No formulário do Resource, inclua campos básicos (nome, model_class, event, is_active) e adicione um Builder ou Repeater do Filament como placeholder estrutural para os campos conditions e actions (apenas a estrutura básica para eu expandir depois).

Retorne o código organizado por arquivos e pastas, explicando brevemente como testar a instalação localmente via symlink do Composer. Retorne o código de forma completa, priorizando as melhores práticas, tipagem estrita (strict_types) e injeção de dependência adequada.