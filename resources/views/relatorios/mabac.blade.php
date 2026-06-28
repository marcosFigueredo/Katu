<x-app-layout>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-1"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="{{ route('relatorios.index') }}">Relatórios</a></li><li class="breadcrumb-item active">MABAC</li></ol></nav>
    <h1 class="h3 fw-bold mb-3" style="color:#00303A;">Rankings MABAC</h1>

    @if ($analises->isEmpty())
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Nenhuma análise MABAC realizada ainda. <a href="{{ route('mabac.index') }}">Criar análise</a>.</div>
    @else

    @foreach ($rankings as $rk)
    @php $analise = $rk['analise']; $resultados = $rk['resultados']; @endphp
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-trophy-fill me-2"></i>{{ $analise->nome }}</h6>
                <div class="text-muted small">{{ $analise->barema?->nome ?? '—' }} · {{ $analise->created_at->format('d/m/Y') }}</div>
            </div>
            <a href="{{ route('mabac.show', $analise) }}" class="btn btn-sm btn-outline-secondary">Ver análise</a>
        </div>
        <div class="card-body p-0">
            @if ($resultados->isEmpty())
                <p class="text-muted small p-3 mb-0">Nenhum resultado calculado.</p>
            @else
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3 text-center" style="width:50px;">Rank</th><th>Trabalho / Discente</th><th>Linha</th><th class="text-center">Escore MABAC</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($resultados as $r)
                        @php
                            $rank = $r->ranking;
                            $medal = match($rank) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $rank };
                        @endphp
                        <tr class="{{ $rank <= 3 ? 'table-active fw-semibold' : '' }}">
                            <td class="text-center ps-3 fw-bold">{{ $rank }}</td>
                            <td>
                                <div class="small fw-semibold">{{ $r->banca?->trabalhoAcademico?->titulo ?? 'Sem título' }}</div>
                                <div class="text-muted" style="font-size:.75rem;">{{ $r->banca?->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user?->name ?? '—' }}</div>
                            </td>
                            <td class="small text-muted">{{ $r->banca?->trabalhoAcademico?->linhaPesquisa?->nome ?? '—' }}</td>
                            <td class="text-center">
                                <span class="badge text-bg-{{ $rank === 1 ? 'success' : ($rank <= 3 ? 'primary' : 'secondary') }}">
                                    {{ number_format($r->escore_final, 4) }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
    @endforeach

    {{-- Análise 33: Distribuição de escores --}}
    @if ($distribuicao->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-bar-chart-fill me-2"></i>Distribuição de Escores por Análise</h6>
        </div>
        <div class="card-body"><canvas id="chartDist" height="80"></canvas></div>
    </div>
    @endif

    @endif
</div>

@if ($distribuicao->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
@php
$palette = ['#00303A','#2d6a73','#5aaab5','#a8d8de','#f4c05a','#e07b39'];
$datasets = [];
foreach ($distribuicao as $i => $d) {
    if ($d['scores']->isEmpty()) continue;
    $datasets[] = [
        'label' => $d['nome'],
        'data' => $d['scores']->values(),
        'backgroundColor' => $palette[$i % count($palette)].'99',
        'borderColor' => $palette[$i % count($palette)],
        'borderWidth' => 1,
    ];
}
$maxCount = $distribuicao->map(fn($d) => $d['scores']->count())->max();
$labels = range(1, $maxCount);
@endphp
new Chart(document.getElementById('chartDist'), {
    type: 'bar',
    data: { labels: @json($labels), datasets: @json($datasets) },
    options: { plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } } }
});
</script>
@endif
</x-app-layout>
