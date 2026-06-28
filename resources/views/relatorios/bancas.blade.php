<x-app-layout>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-1">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('relatorios.index') }}">Relatórios</a></li>
            <li class="breadcrumb-item active">Desempenho das Bancas</li>
        </ol>
    </nav>
    <h1 class="h3 fw-bold mb-3" style="color:#00303A;">Desempenho das Bancas</h1>

    @include('relatorios._filtro')

    @if ($bancas->isEmpty())
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Nenhuma banca finalizada no período selecionado.</div>
    @else

    {{-- Análise 1: Resultados por tipo --}}
    <div class="row g-3 mb-4">
        @foreach (['qualificacao'=>'Qualificação','defesa'=>'Defesa'] as $tipo => $label)
        @php $g = $resultadosPorTipo->get($tipo, collect()) @endphp
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-bar-chart-fill me-2"></i>Resultados — {{ $label }}</h6>
                </div>
                <div class="card-body">
                    @foreach (['aprovado'=>['success','Aprovado'],'aprovado_modificacoes'=>['warning','Aprovado c/ mod.'],'reprovado'=>['danger','Reprovado']] as $res => [$cor,$rlabel])
                    @php $n = $g->get($res, 0); $tot = $g->sum() ?: 1; @endphp
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span class="badge text-bg-{{ $cor }} me-2">{{ $rlabel }}</span>
                        <div class="flex-grow-1 mx-2">
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar bg-{{ $cor }}" style="width:{{ round($n/$tot*100) }}%"></div>
                            </div>
                        </div>
                        <span class="fw-semibold">{{ $n }} <span class="text-muted">({{ round($n/$tot*100,1) }}%)</span></span>
                    </div>
                    @endforeach
                    <div class="text-muted small mt-2">Total: <strong>{{ $g->sum() }}</strong></div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Análise 6: Série temporal --}}
    @if ($serieTemporal->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-graph-up me-2"></i>Evolução da Nota Média Mensal</h6>
        </div>
        <div class="card-body">
            <canvas id="chartTemporal" height="80"></canvas>
        </div>
    </div>
    @endif

    {{-- Análise 2: Nota por avaliador --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-person-lines-fill me-2"></i>Nota Média por Avaliador</h6>
        </div>
        <div class="card-body p-0">
            @if ($notasPorAvaliador->isEmpty())
                <p class="text-muted small p-3 mb-0">Sem dados de avaliação no período.</p>
            @else
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Avaliador</th><th class="text-center">Bancas</th><th class="text-center">Média</th><th class="text-center">Mín</th><th class="text-center">Máx</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($notasPorAvaliador as $nome => $d)
                        <tr>
                            <td class="ps-3 fw-semibold">{{ $nome }}</td>
                            <td class="text-center">{{ $d['total'] }}</td>
                            <td class="text-center">
                                <span class="badge text-bg-{{ $d['media'] >= 7 ? 'success' : ($d['media'] >= 5 ? 'warning' : 'danger') }}">
                                    {{ number_format($d['media'],2) }}
                                </span>
                            </td>
                            <td class="text-center text-muted small">{{ $d['min'] }}</td>
                            <td class="text-center text-muted small">{{ $d['max'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- Análise 3: Variância intra-banca --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-distribute-vertical me-2"></i>Concordância entre Avaliadores (Desvio-Padrão Intra-Banca)</h6>
        </div>
        <div class="card-body">
            @php $faixas = ['< 0,5 (Alta concordância)'=>$distribuicaoVariancia[0],'0,5–1,0 (Boa concordância)'=>$distribuicaoVariancia[1],'1,0–1,5 (Divergência moderada)'=>$distribuicaoVariancia[2],'> 1,5 (Alta divergência)'=>$distribuicaoVariancia[3]]; $totV = array_sum($faixas) ?: 1; @endphp
            @foreach ($faixas as $label => $n)
            @php $pct = round($n/$totV*100); @endphp
            <div class="d-flex align-items-center gap-2 mb-2 small">
                <div style="width:200px;">{{ $label }}</div>
                <div class="flex-grow-1">
                    <div class="progress" style="height:10px;">
                        <div class="progress-bar" style="width:{{ $pct }}%; background:#00303A;"></div>
                    </div>
                </div>
                <div class="fw-semibold" style="width:60px;">{{ $n }} <span class="text-muted">({{ $pct }}%)</span></div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Análise 4: Por item do barema --}}
    @if ($notasPorItem->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-list-check me-2"></i>Aproveitamento por Item do Barema</h6>
        </div>
        <div class="card-body">
            <canvas id="chartItens" height="120"></canvas>
        </div>
    </div>
    @endif

    {{-- Análise 5 + 7 em linha --}}
    <div class="row g-3 mb-3">
        @if ($notasPorSecao->isNotEmpty())
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-grid-3x3-gap me-2"></i>Aproveitamento por Seção</h6>
                </div>
                <div class="card-body">
                    @foreach ($notasPorSecao as $secao => $d)
                    @php $ap = $d['aproveitamento']; @endphp
                    <div class="mb-2 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span>{{ $secao }}</span>
                            <span class="fw-semibold">{{ $ap }}%</span>
                        </div>
                        <div class="progress" style="height:8px;">
                            <div class="progress-bar {{ $ap >= 70 ? 'bg-success' : ($ap >= 50 ? 'bg-warning' : 'bg-danger') }}" style="width:{{ $ap }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-diagram-3 me-2"></i>Por Linha de Pesquisa</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th class="ps-3">Linha</th><th>Total</th><th>Média</th><th>Aprovação</th></tr></thead>
                            <tbody>
                                @foreach ($porLinha as $linha => $d)
                                <tr>
                                    <td class="ps-3 small fw-semibold">{{ $linha }}</td>
                                    <td class="small">{{ $d['total'] }}</td>
                                    <td class="small"><span class="badge text-bg-{{ ($d['media']??0) >= 7 ? 'success' : 'warning' }}">{{ number_format($d['media']??0,2) }}</span></td>
                                    <td class="small">{{ $d['total'] > 0 ? round($d['aprovadas']/$d['total']*100,0) : 0 }}%</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @endif
</div>

@if ($serieTemporal->isNotEmpty() || $notasPorItem->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
@if ($serieTemporal->isNotEmpty())
new Chart(document.getElementById('chartTemporal'), {
    type: 'line',
    data: {
        labels: @json($serieTemporal->keys()),
        datasets: [{ label: 'Nota Média', data: @json($serieTemporal->values()), borderColor:'#00303A', backgroundColor:'rgba(0,48,58,.1)', tension:.3, fill:true }]
    },
    options: { scales: { y: { min:0, max:10, ticks:{ stepSize:1 } } }, plugins:{ legend:{ display:false } } }
});
@endif
@if ($notasPorItem->isNotEmpty())
new Chart(document.getElementById('chartItens'), {
    type: 'bar',
    data: {
        labels: @json($notasPorItem->pluck('nome')),
        datasets: [{ label:'Aproveitamento (%)', data: @json($notasPorItem->pluck('aproveitamento')),
            backgroundColor: @json($notasPorItem->map(fn($i) => $i['aproveitamento'] >= 70 ? '#198754' : ($i['aproveitamento'] >= 50 ? '#ffc107' : '#dc3545'))->values()) }]
    },
    options: { indexAxis:'y', scales:{ x:{ min:0, max:100 } }, plugins:{ legend:{ display:false } } }
});
@endif
</script>
@endif
</x-app-layout>
