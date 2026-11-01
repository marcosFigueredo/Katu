{{-- Projeto de pesquisa do docente permanente (só leitura: quem cadastra é o próprio docente). --}}
@use('App\Models\ProjetoPesquisa')
<div class="card border-0 shadow-sm mt-4" id="projeto-pesquisa">
    <div class="card-body">
        <h2 class="h5 fw-bold mb-3" style="color:#00303A;">
            <i class="bi bi-journal-text me-1"></i>Projeto de pesquisa
        </h2>
        @php $ativo = $projetosDocente->firstWhere('situacao', 'ativo'); @endphp
        @if ($ativo)
            <div class="fw-semibold">{{ $ativo->titulo }}</div>
            <div class="small text-muted mb-2">
                desde {{ $ativo->inicio_em?->format('d/m/Y') }} · {{ $ativo->fim_em ? 'fim previsto '.$ativo->fim_em->format('d/m/Y') : 'em andamento' }}
            </div>
            <p class="small mb-3" style="white-space: pre-line;">{{ $ativo->descricao }}</p>
        @else
            <div class="alert alert-warning py-2">Docente permanente sem projeto de pesquisa cadastrado. O próprio docente cadastra na Área do Docente.</div>
        @endif
        @foreach ($projetosDocente->where('situacao', 'concluido') as $projeto)
            <div class="small text-muted">
                {{ ProjetoPesquisa::SITUACOES['concluido'] }}: {{ $projeto->titulo }}
                ({{ $projeto->inicio_em?->format('d/m/Y') }} a {{ $projeto->fim_em?->format('d/m/Y') ?? '—' }})
            </div>
        @endforeach
    </div>
</div>
