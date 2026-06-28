<x-app-layout>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-1"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="{{ route('relatorios.index') }}">Relatórios</a></li><li class="breadcrumb-item active">IEO</li></ol></nav>
    <h1 class="h3 fw-bold mb-3" style="color:#00303A;">Índice de Excelência da Orientação (IEO)</h1>

    @if ($avaliacoes->isEmpty())
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Nenhuma avaliação registrada ainda.</div>
    @else

    {{-- KPIs --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-3 text-center">
            <div class="card border-0 shadow-sm p-3">
                <div class="fs-2 fw-bold {{ ($ieoGeral??0) >= 90 ? 'text-success' : (($ieoGeral??0) >= 70 ? 'text-warning' : 'text-danger') }}">{{ $ieoGeral ?? '—' }}</div>
                <div class="text-muted small">IEO médio do programa</div>
                <div class="text-muted" style="font-size:.72rem;">Meta: 90</div>
            </div>
        </div>
        <div class="col-sm-3 text-center">
            <div class="card border-0 shadow-sm p-3">
                <div class="fs-2 fw-bold" style="color:#00303A;">{{ $avaliacoes->count() }}</div>
                <div class="text-muted small">Respostas totais</div>
            </div>
        </div>
        @foreach (['Excelente'=>'success','Muito bom'=>'primary','Bom'=>'info','Regular'=>'warning','Crítico'=>'danger'] as $class => $cor)
        @if (isset($distribuicao[$class]) && $distribuicao[$class] > 0)
        <div class="col-sm-3 col-xl-auto text-center">
            <div class="card border-0 shadow-sm p-3">
                <div class="fs-2 fw-bold text-{{ $cor }}">{{ $distribuicao[$class] }}</div>
                <div class="text-muted small">{{ $class }}</div>
            </div>
        </div>
        @endif
        @endforeach
    </div>

    {{-- Análise 21: Categorias do programa (radar) --}}
    <div class="row g-3 mb-3">
        <div class="col-md-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-radar me-2"></i>Perfil por Categoria — Programa</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chartRadar" style="max-height:280px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-bar-chart-steps me-2"></i>Médias por Categoria</h6>
                </div>
                <div class="card-body">
                    @foreach ($categoriaPrograma as $cat => $val)
                    <div class="d-flex align-items-center gap-2 mb-2 small">
                        <div style="width:200px;">{{ $cat }}</div>
                        <div class="flex-grow-1"><div class="progress" style="height:10px;"><div class="progress-bar {{ ($val??0) >= 90 ? 'bg-success' : (($val??0) >= 70 ? 'bg-warning' : 'bg-danger') }}" style="width:{{ $val ?? 0 }}%"></div></div></div>
                        <div class="fw-semibold" style="width:40px;">{{ $val ?? '—' }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Análise 20: Ranking orientadores --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-trophy-fill me-2"></i>Ranking por Orientador <span class="text-muted fw-normal small">(mín. 3 avaliações)</span></h6>
        </div>
        <div class="card-body p-0">
            @if ($porOrientador->isEmpty())
                <p class="text-muted small p-3 mb-0">Nenhum orientador com ≥ 3 avaliações ainda.</p>
            @else
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">#</th><th>Orientador</th><th>Resp.</th><th class="text-center">IEO</th><th class="text-center">Classificação</th><th class="text-center">Cat1</th><th class="text-center">Cat2</th><th class="text-center">Cat3</th><th class="text-center">Cat4</th><th class="text-center">Cat5</th><th class="text-center">Cat6</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($porOrientador as $i => $r)
                        @php
                            $ieo = $r['ieo_medio'];
                            $class = $ieo >= 90 ? ['success','Excelente'] : ($ieo >= 80 ? ['primary','Muito bom'] : ($ieo >= 70 ? ['info','Bom'] : ($ieo >= 60 ? ['warning','Regular'] : ['danger','Crítico'])));
                        @endphp
                        <tr>
                            <td class="ps-3 text-muted small">{{ $i+1 }}</td>
                            <td class="fw-semibold">{{ $r['nome'] }}</td>
                            <td>{{ $r['total'] }}</td>
                            <td class="text-center fw-bold">{{ $ieo }}</td>
                            <td class="text-center"><span class="badge text-bg-{{ $class[0] }}">{{ $class[1] }}</span></td>
                            @foreach (['cat1','cat2','cat3','cat4','cat5','cat6'] as $cat)
                            <td class="text-center small {{ ($r[$cat]??0) < 75 ? 'text-danger fw-semibold' : '' }}">{{ $r[$cat] }}</td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    @endif
</div>

@if (!$avaliacoes->isEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartRadar'), {
    type: 'radar',
    data: {
        labels: @json(array_keys($categoriaPrograma)),
        datasets: [{
            label: 'IEO Programa',
            data: @json(array_values($categoriaPrograma)),
            backgroundColor: 'rgba(0,48,58,.15)',
            borderColor: '#00303A',
            pointBackgroundColor: '#00303A'
        }]
    },
    options: { scales:{ r:{ min:0, max:100, ticks:{ stepSize:25 } } }, plugins:{ legend:{ display:false } } }
});
</script>
@endif
</x-app-layout>
