<x-app-layout>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-1"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="{{ route('relatorios.index') }}">Relatórios</a></li><li class="breadcrumb-item active">Percurso do Discente</li></ol></nav>
    <h1 class="h3 fw-bold mb-3" style="color:#00303A;">Percurso do Discente</h1>

    @include('relatorios._filtro')

    {{-- KPIs de eficiência --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-2 fw-bold {{ ($eficienciaQual??0) >= 70 ? 'text-success' : 'text-warning' }}">{{ $eficienciaQual !== null ? $eficienciaQual.'%' : '—' }}</div>
                <div class="text-muted small">Qualificações no prazo do curso</div>
                <div class="text-muted" style="font-size:.72rem;">Meta: 70%</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-2 fw-bold {{ ($eficienciaDefesa??0) >= 70 ? 'text-success' : 'text-warning' }}">{{ $eficienciaDefesa !== null ? $eficienciaDefesa.'%' : '—' }}</div>
                <div class="text-muted small">Defesas no prazo do curso</div>
                <div class="text-muted" style="font-size:.72rem;">Meta: 70%</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-2 fw-bold" style="color:#00303A;">{{ $mesesQual->isNotEmpty() ? round($mesesQual->avg(),1) : '—' }}</div>
                <div class="text-muted small">Tempo médio qualificação (meses)</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-2 fw-bold" style="color:#00303A;">{{ $mesesDefesa->isNotEmpty() ? round($mesesDefesa->avg(),1) : '—' }}</div>
                <div class="text-muted small">Tempo médio até defesa (meses)</div>
            </div>
        </div>
    </div>

    {{-- Análise 16: Histogramas --}}
    <div class="row g-3 mb-3">
        @if (!empty($histQual))
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-bar-chart-fill me-2"></i>Distribuição — Tempo até Qualificação</h6>
                </div>
                <div class="card-body"><canvas id="histQual" height="160"></canvas></div>
                <div class="card-footer bg-white text-muted small">Linha vermelha = referência de 18 meses (mestrado); o percentual acima usa o prazo de cada curso</div>
            </div>
        </div>
        @endif
        @if (!empty($histDefesa))
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-bar-chart-fill me-2"></i>Distribuição — Tempo até Defesa</h6>
                </div>
                <div class="card-body"><canvas id="histDefesa" height="160"></canvas></div>
                <div class="card-footer bg-white text-muted small">Linha vermelha = referência de 24 meses (mestrado); o percentual acima usa o prazo de cada curso</div>
            </div>
        </div>
        @endif
    </div>

    {{-- Análise 17: Funil --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-filter me-2"></i>Funil de Progresso dos Discentes</h6>
        </div>
        <div class="card-body">
            @php
            $fases = ['em_andamento'=>['Pesquisa em andamento','secondary'],'qualificado'=>['Qualificado','info'],'defendido'=>['Defendido','primary'],'concluido'=>['Concluído','success'],'cancelado'=>['Cancelado','danger']];
            $totFunil = $funil->sum() ?: 1;
            @endphp
            @foreach ($fases as $status => [$label,$cor])
            @php $n = $funil->get($status,0); @endphp
            <div class="d-flex align-items-center gap-3 mb-2">
                <div style="width:160px;" class="small fw-semibold">{{ $label }}</div>
                <div class="flex-grow-1">
                    <div class="progress" style="height:20px;">
                        <div class="progress-bar bg-{{ $cor }}" style="width:{{ round($n/$totFunil*100) }}%">
                            @if($n > 0) {{ $n }} @endif
                        </div>
                    </div>
                </div>
                <div class="small fw-bold" style="width:40px;">{{ $n }}</div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Análise 18: Cohort --}}
    @if ($cohort->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-people-fill me-2"></i>Análise por Coorte de Ingresso</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Ano de ingresso</th><th>Ingressantes</th><th>Qualificados</th><th>% Qualificados</th><th>Defendidos</th><th>% Defendidos</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($cohort as $row)
                        <tr>
                            <td class="ps-3 fw-semibold">{{ $row->ano_ingresso }}</td>
                            <td>{{ $row->total }}</td>
                            <td>{{ $row->qualificados }}</td>
                            <td>{{ $row->total > 0 ? round($row->qualificados/$row->total*100,0) : 0 }}%</td>
                            <td>{{ $row->defendidos }}</td>
                            <td>{{ $row->total > 0 ? round($row->defendidos/$row->total*100,0) : 0 }}%</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Análise 19: Por linha --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-diagram-3 me-2"></i>Trabalhos por Linha de Pesquisa</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Linha</th><th>Total</th><th>Mestrado</th><th>Doutorado</th><th>Concluídos</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($porLinha as $linha => $d)
                        <tr>
                            <td class="ps-3 fw-semibold">{{ $linha }}</td>
                            <td>{{ $d['total'] }}</td>
                            <td>{{ $d['mestrado'] }}</td>
                            <td>{{ $d['doutorado'] }}</td>
                            <td>{{ $d['concluidos'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
@if (!empty($histQual))
new Chart(document.getElementById('histQual'), {
    type: 'bar',
    data: {
        labels: @json(array_keys($histQual)),
        datasets: [{
            label:'Qualificações', data: @json(array_values($histQual)),
            backgroundColor: @json(array_map(fn($l) => str_contains($l,'15') || str_contains($l,'18') ? '#dc3545' : '#00303A', array_keys($histQual)))
        }]
    },
    options: { plugins:{ legend:{ display:false },
        annotation:{ annotations:[{ type:'line', scaleID:'x', value:'15–18m', borderColor:'red', borderWidth:2, label:{ content:'18m', display:true } }] } }
    }
});
@endif
@if (!empty($histDefesa))
new Chart(document.getElementById('histDefesa'), {
    type: 'bar',
    data: {
        labels: @json(array_keys($histDefesa)),
        datasets: [{
            label:'Defesas', data: @json(array_values($histDefesa)),
            backgroundColor: @json(array_map(fn($l) => str_contains($l,'21') || str_contains($l,'24') ? '#dc3545' : '#2d6a73', array_keys($histDefesa)))
        }]
    },
    options: { plugins:{ legend:{ display:false } } }
});
@endif
</script>
</x-app-layout>
