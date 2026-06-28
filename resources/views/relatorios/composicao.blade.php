<x-app-layout>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-1"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="{{ route('relatorios.index') }}">Relatórios</a></li><li class="breadcrumb-item active">Composição das Bancas</li></ol></nav>
    <h1 class="h3 fw-bold mb-3" style="color:#00303A;">Composição das Bancas — B1 a B8</h1>

    @include('relatorios._filtro')

    @if ($total === 0)
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Nenhuma banca com indicadores calculados no período.</div>
    @else

    {{-- Análise 8: Atendimento B1–B8 --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-shield-check me-2"></i>% de Atendimento por Indicador ({{ $total }} bancas)</h6>
        </div>
        <div class="card-body">
            <canvas id="chartB" height="80"></canvas>
        </div>
    </div>

    {{-- Análise 9: Evolução --}}
    @if ($evolucaoIndice->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-graph-up-arrow me-2"></i>Evolução do Índice de Composição por Mês</h6>
        </div>
        <div class="card-body">
            <canvas id="chartEvolucao" height="80"></canvas>
        </div>
    </div>
    @endif

    {{-- Análise 10: Correlação --}}
    @if ($correlacaoData->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-scatter-chart me-2"></i>Correlação: Índice de Composição × Nota do Trabalho</h6>
        </div>
        <div class="card-body">
            <canvas id="chartCorr" height="100"></canvas>
        </div>
        <div class="card-footer bg-white text-muted small">Cada ponto representa uma banca. Eixo X = índice B1–B8 (0–1), Eixo Y = nota média (0–10).</div>
    </div>
    @endif

    {{-- Por linha de pesquisa --}}
    @if ($indicadoresPorLinha->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-diagram-3 me-2"></i>Por Linha de Pesquisa</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Linha</th><th>Bancas</th><th>Índice Médio</th><th>B1 ORCID</th><th>B2 Externo</th><th>B3 Diversidade</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($indicadoresPorLinha as $linha => $d)
                        <tr>
                            <td class="ps-3 fw-semibold">{{ $linha }}</td>
                            <td>{{ $d['total'] }}</td>
                            <td><span class="badge text-bg-{{ $d['indice'] >= 0.75 ? 'success' : ($d['indice'] >= 0.5 ? 'warning' : 'danger') }}">{{ number_format($d['indice'],3) }}</span></td>
                            <td>{{ $d['b1'] }}%</td>
                            <td>{{ $d['b2'] }}%</td>
                            <td>{{ $d['b3'] }}%</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    @endif
</div>

@if ($total > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
@php
$bLabels = ['B1 ORCID completo','B2 Avaliador externo','B3 Diversidade inst.','B4 Aderência temática','B5 Diversidade formação','B6 Rotatividade','B7 Int/Ext equilibrado','B8 Aderência linha'];
@endphp
new Chart(document.getElementById('chartB'), {
    type: 'bar',
    data: {
        labels: @json($bLabels),
        datasets: [{ label:'Atendimento (%)', data: @json(array_values($atendimento)),
            backgroundColor: @json(array_map(fn($v) => $v >= 75 ? '#198754' : ($v >= 50 ? '#ffc107' : '#dc3545'), array_values($atendimento))) }]
    },
    options: { scales:{ y:{ min:0, max:100 } }, plugins:{ legend:{ display:false } } }
});

@if ($evolucaoIndice->isNotEmpty())
new Chart(document.getElementById('chartEvolucao'), {
    type: 'line',
    data: {
        labels: @json($evolucaoIndice->keys()),
        datasets: [{ label:'Índice médio', data: @json($evolucaoIndice->values()), borderColor:'#00303A', tension:.3, fill:false }]
    },
    options: { scales:{ y:{ min:0, max:1 } }, plugins:{ legend:{ display:false } } }
});
@endif

@if ($correlacaoData->isNotEmpty())
new Chart(document.getElementById('chartCorr'), {
    type: 'scatter',
    data: { datasets: [{ label:'Banca', data: @json($correlacaoData), backgroundColor:'rgba(0,48,58,.6)', pointRadius:5 }] },
    options: { scales:{ x:{ min:0, max:1, title:{ display:true, text:'Índice de composição' } }, y:{ min:0, max:10, title:{ display:true, text:'Nota média' } } } }
});
@endif
</script>
@endif
</x-app-layout>
