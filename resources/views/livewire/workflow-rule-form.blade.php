<div>
    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
            <button type="submit">
                {{ $ruleId ? 'Atualizar' : 'Criar' }}
            </button>

            @if($ruleId)
                <button type="button" wire:click="delete" wire:confirm="Tem certeza que deseja excluir?">
                    Excluir
                </button>
            @endif
        </div>
    </form>
</div>
