<x-app-layout>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-1"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="{{ route('relatorios.index') }}">Relatórios</a></li><li class="breadcrumb-item active">Autoavaliação A01–A16</li></ol></nav>
    <h1 class="h3 fw-bold mb-3" style="color:#00303A;">Autoavaliação do Programa — A01 a A16</h1>

    @if ($periodos->isEmpty())
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Nenhum período de autoavaliação concluído ainda. <a href="{{ route('autoavaliacao.index') }}">Iniciar autoavaliação</a>.</div>
    @else

    {{-- Análise 29: Indicadores do período atual --}}
    @if ($periodoAtual)
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-clipboard2-data-fill me-2"></i>Indicadores — {{ $periodoAtual->nome }}</h6>
            <span class="badge text-bg-success">{{ $periodoAtual->indicadores->count() }} indicadores</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3" style="width:80px;">Código</th><th>Descrição</th><th class="text-center">Valor</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($indicadoresAtuais->sortKeys() as $codigo => $ind)
                        <tr>
                            <td class="ps-3 fw-bold small">{{ $codigo }}</td>
                            <td class="small">{{ $ind->descricao ?? $ind->codigo }}</td>
                            <td class="text-center fw-semibold">
                                @if ($ind->valor_numerico !== null)
                                    {{ number_format($ind->valor_numerico, 1) }}{{ str_contains($ind->unidade ?? '', '%') ? '%' : '' }}
                                @else
                                    <span class="text-muted small">{{ Str::limit($ind->valor_texto ?? '—', 30) }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($ind->status_semaforo ?? null)
                                    @php $sc = ['verde'=>'success','amarelo'=>'warning','vermelho'=>'danger'][$ind->status_semaforo] ?? 'secondary'; @endphp
                                    <span class="badge text-bg-{{ $sc }}">{{ $ind->status_semaforo }}</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Análise 30: Histórico --}}
    @if ($historico->count() > 1)
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-graph-up-arrow me-2"></i>Evolução dos Indicadores entre Períodos</h6>
        </div>
        <div class="card-body"><canvas id="chartHistorico" height="100"></canvas></div>
    </div>
    @endif

    {{-- Análise 31: A12 por linha --}}
    @if ($a12)
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-diagram-3 me-2"></i>A12 — Produção Bibliométrica por Linha de Pesquisa</h6>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light"><tr><th class="ps-3">Linha</th><th class="text-center">Média H</th><th class="text-center">Média Citações</th><th class="text-center">Obras</th><th class="text-center">% DOI</th></tr></thead>
                <tbody>
                    @foreach ($a12 as $linha => $dados)
                    <tr>
                        <td class="ps-3 fw-semibold small">{{ $linha }}</td>
                        <td class="text-center">{{ $dados['media_h'] ?? '—' }}</td>
                        <td class="text-center">{{ $dados['media_citacoes'] ?? '—' }}</td>
                        <td class="text-center">{{ $dados['obras'] ?? '—' }}</td>
                        <td class="text-center">{{ isset($dados['pct_doi']) ? $dados['pct_doi'].'%' : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @endif
</div>

@if ($historico->count() > 1)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
@php
$codigosNumericos = $historico->first()->keys()->toArray();
$datasets = [];
$palette = ['#00303A','#2d6a73','#5aaab5','#f4c05a','#e07b39','#c03a2b','#a8d8de'];
foreach ($codigosNumericos as $i => $cod) {
    $data = [];
    foreach ($historico as $periodo => $indicadores) {
        $data[] = $indicadores->get($cod)?->valor_numerico;
    }
    $datasets[] = ['label' => $cod, 'data' => $data, 'borderColor' => $palette[$i % count($palette)], 'tension' => 0.3, 'fill' => false];
}
@endphp
new Chart(document.getElementById('chartHistorico'), {
    type: 'line',
    data: { labels: @json($historico->keys()), datasets: @json($datasets) },
    options: { plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } } }
});
</script>
@endif
</x-app-layout>
