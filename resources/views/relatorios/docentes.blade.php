<x-app-layout>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-1"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="{{ route('relatorios.index') }}">Relatórios</a></li><li class="breadcrumb-item active">Bibliometria dos Docentes</li></ol></nav>
    <h1 class="h3 fw-bold mb-3" style="color:#00303A;">Bibliometria dos Docentes</h1>

    @include('relatorios._filtro')

    <ul class="nav nav-tabs mb-4 d-print-none" id="tabDocentes">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-orientadores"><i class="bi bi-mortarboard-fill me-1"></i>Orientadores</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-avaliadores"><i class="bi bi-clipboard2-check-fill me-1"></i>Avaliadores</a></li>
    </ul>

    @php
    $painelDocentes = function($dados, $papel) {
    @endphp

    <div class="tab-content">
        @foreach (['orientadores'=>$dadosOrientadores,'avaliadores'=>$dadosAvaliadores] as $key => $dados)
        <div class="tab-pane {{ $key === 'orientadores' ? 'show active' : '' }}" id="tab-{{ $key }}">

            {{-- Análise 15: Cobertura ORCID --}}
            <div class="row g-3 mb-4">
                @php $cob = $dados['cobertura']; $totCob = array_sum($cob) ?: 1; @endphp
                <div class="col-sm-4 text-center">
                    <div class="card border-0 shadow-sm p-3">
                        <div class="fs-2 fw-bold text-success">{{ $cob['com_orcid'] }}</div>
                        <div class="text-muted small">ORCID sincronizado</div>
                    </div>
                </div>
                <div class="col-sm-4 text-center">
                    <div class="card border-0 shadow-sm p-3">
                        <div class="fs-2 fw-bold text-danger">{{ $cob['com_erro'] }}</div>
                        <div class="text-muted small">Erro na sincronização</div>
                    </div>
                </div>
                <div class="col-sm-4 text-center">
                    <div class="card border-0 shadow-sm p-3">
                        <div class="fs-2 fw-bold text-secondary">{{ $cob['sem_snapshot'] }}</div>
                        <div class="text-muted small">Sem snapshot</div>
                    </div>
                </div>
            </div>

            {{-- Análise 11: Ranking bibliométrico --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-trophy-fill me-2"></i>Ranking Bibliométrico</h6>
                </div>
                <div class="card-body p-0">
                    @if ($dados['ranking']->isEmpty())
                        <p class="text-muted small p-3 mb-0">Nenhum snapshot ORCID disponível.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th class="ps-3">#</th><th>Nome</th><th class="text-center">Índice H</th><th class="text-center">Citações</th><th class="text-center">i10</th><th class="text-center">Obras</th><th class="text-center">% DOI</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($dados['ranking'] as $i => $d)
                                <tr>
                                    <td class="ps-3 text-muted small">{{ $i+1 }}</td>
                                    <td class="fw-semibold">{{ $d['nome'] }}</td>
                                    <td class="text-center"><span class="badge text-bg-{{ ($d['h_index']??0) >= 10 ? 'success' : (($d['h_index']??0) >= 5 ? 'warning' : 'secondary') }}">{{ $d['h_index'] ?? '—' }}</span></td>
                                    <td class="text-center">{{ number_format($d['total_citacoes'] ?? 0) }}</td>
                                    <td class="text-center">{{ $d['i10_index'] ?? '—' }}</td>
                                    <td class="text-center">{{ $d['total_obras'] ?? 0 }}</td>
                                    <td class="text-center">
                                        @if (($d['total_obras'] ?? 0) > 0)
                                            {{ round(($d['total_doi'] ?? 0) / $d['total_obras'] * 100) }}%
                                        @else —
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Análises 12 e 13 lado a lado --}}
            <div class="row g-3 mb-3">
                @if ($dados['publicacoesPorAno']->isNotEmpty())
                <div class="col-md-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-calendar3 me-2"></i>Publicações por Ano</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="chartAnos-{{ $key }}" height="140"></canvas>
                        </div>
                    </div>
                </div>
                @endif
                @if ($dados['tiposObra']->isNotEmpty())
                <div class="col-md-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-pie-chart-fill me-2"></i>Tipos de Obra</h6>
                        </div>
                        <div class="card-body d-flex align-items-center justify-content-center">
                            <canvas id="chartTipos-{{ $key }}" style="max-height:220px;"></canvas>
                        </div>
                    </div>
                </div>
                @endif
            </div>

        </div>
        @endforeach
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
@php
$palette = ['#00303A','#2d6a73','#5aaab5','#a8d8de','#f4c05a','#e07b39','#c03a2b'];
@endphp

@foreach (['orientadores'=>$dadosOrientadores,'avaliadores'=>$dadosAvaliadores] as $key => $dados)
@if ($dados['publicacoesPorAno']->isNotEmpty())
new Chart(document.getElementById('chartAnos-{{ $key }}'), {
    type: 'bar',
    data: {
        labels: @json($dados['publicacoesPorAno']->keys()),
        datasets: [{ label:'Publicações', data: @json($dados['publicacoesPorAno']->values()), backgroundColor:'#00303A' }]
    },
    options: { plugins:{ legend:{ display:false } } }
});
@endif
@if ($dados['tiposObra']->isNotEmpty())
new Chart(document.getElementById('chartTipos-{{ $key }}'), {
    type: 'doughnut',
    data: {
        labels: @json($dados['tiposObra']->keys()),
        datasets: [{ data: @json($dados['tiposObra']->values()), backgroundColor: @json($palette) }]
    },
    options: { plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } } }
});
@endif
@endforeach
</script>
</x-app-layout>
