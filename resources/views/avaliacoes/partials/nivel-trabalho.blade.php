{{-- Para o orientador: como a banca interpretou o trabalho (nível nas 5 classes e perfil por dimensão). --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 fw-bold mb-1" style="color:#00303A;">
            <i class="bi bi-bar-chart-line me-1"></i>Como a banca interpretou o trabalho
        </h2>
        <p class="small text-muted mb-3">
            Nível calculado a partir das notas dos avaliadores e da produção associada, com os pesos definidos pela CPA.
            Não altera o resultado da banca.
        </p>
        <div class="mb-3">
            <span class="me-2">Nível:</span>
            @include('cpa._classe', ['classe' => $nivelTrabalho['classe']])
            @if ($nivelTrabalho['indice'] !== null)
                <span class="small text-muted ms-2">índice {{ number_format($nivelTrabalho['indice'], 2, ',', '') }} de 10</span>
            @endif
            @foreach ($nivelTrabalho['regras'] as $regra)
                <div class="small text-danger mt-1">{{ $regra }}</div>
            @endforeach
        </div>
        @foreach (\App\Models\PeriodoQualidade::rotulos('dimensao') as $codigo => $nome)
            <div class="row align-items-center mb-1">
                <div class="col-sm-4 small">{{ $nome }}</div>
                <div class="col-sm-8">@include('cpa._barra', ['nota' => $nivelTrabalho['dimensoes'][$codigo]])</div>
            </div>
        @endforeach
    </div>
</div>
