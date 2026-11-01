{{-- Conferência informativa da publicação no OpenAlex (não invalida a produção). --}}
@if ($publicacao->openalex_situacao === 'encontrada')
    <span class="badge bg-success-subtle text-success-emphasis">
        <i class="bi bi-check-circle me-1"></i>Encontrada no OpenAlex
        @if ($publicacao->openalex_citacoes !== null)
            · {{ $publicacao->openalex_citacoes }} citação(ões)
        @endif
    </span>
@elseif ($publicacao->openalex_situacao === 'nao_encontrada')
    <span class="badge bg-secondary-subtle text-secondary-emphasis" title="O OpenAlex não indexa toda a produção; a publicação continua válida.">
        Não encontrada no OpenAlex — não invalida a produção
    </span>
@else
    <span class="badge bg-light text-muted">Conferência no OpenAlex pendente</span>
@endif
