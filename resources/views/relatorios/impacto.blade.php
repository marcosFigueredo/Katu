<x-app-layout>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-1"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="{{ route('relatorios.index') }}">Relatórios</a></li><li class="breadcrumb-item active">Impacto das Dissertações</li></ol></nav>
    <h1 class="h3 fw-bold mb-3" style="color:#00303A;">Impacto das Dissertações/Teses</h1>

    @if ($totalTrabalhos === 0)
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Nenhum impacto registrado ainda.</div>
    @else

    {{-- KPI total --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-3 text-center">
            <div class="card border-0 shadow-sm p-3">
                <div class="fs-2 fw-bold" style="color:#00303A;">{{ $totalTrabalhos }}</div>
                <div class="text-muted small">Trabalhos com impacto registrado</div>
            </div>
        </div>
        @foreach ($contagemDimensao as $chave => $n)
        <div class="col-sm-3 col-xl-auto text-center">
            <div class="card border-0 shadow-sm p-3">
                <div class="fs-2 fw-bold" style="color:#00303A;">{{ $n }}</div>
                <div class="text-muted small">{{ $dimensoes[$chave] }}</div>
                <div class="text-muted" style="font-size:.72rem;">{{ $totalTrabalhos > 0 ? round($n/$totalTrabalhos*100).'%' : '—' }}</div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Análise 23: Contagem por dimensão --}}
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-bar-chart-horizontal-fill me-2"></i>Dimensões de Impacto mais Frequentes</h6>
                </div>
                <div class="card-body"><canvas id="chartDim" height="180"></canvas></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-table me-2"></i>Resumo por Dimensão</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th class="ps-3">Dimensão</th><th class="text-center">Trabalhos</th><th class="text-center">Cobertura</th></tr></thead>
                        <tbody>
                            @foreach ($dimensoes as $chave => $label)
                            @php $n = $contagemDimensao[$chave] ?? 0; @endphp
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $label }}</td>
                                <td class="text-center">{{ $n }}</td>
                                <td class="text-center">
                                    <div class="progress" style="height:8px;">
                                        <div class="progress-bar" style="width:{{ $totalTrabalhos > 0 ? round($n/$totalTrabalhos*100) : 0 }}%; background:#00303A;"></div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Análise 24: Por linha de pesquisa --}}
    @if ($porLinha->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-diagram-3 me-2"></i>Impacto por Linha de Pesquisa</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Linha</th>
                            @foreach ($dimensoes as $label) <th class="text-center small">{{ $label }}</th> @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($porLinha as $linha => $dims)
                        <tr>
                            <td class="ps-3 fw-semibold small">{{ $linha }}</td>
                            @foreach ($dims as $n)
                            <td class="text-center small">{{ $n > 0 ? $n : '—' }}</td>
                            @endforeach
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

@if ($totalTrabalhos > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartDim'), {
    type: 'bar',
    data: {
        labels: @json(array_values($dimensoes)),
        datasets: [{ label:'Trabalhos', data: @json(array_values($contagemDimensao->toArray())),
            backgroundColor: ['#00303A','#2d6a73','#5aaab5','#a8d8de','#f4c05a','#e07b39','#c03a2b'] }]
    },
    options: { indexAxis:'y', plugins:{ legend:{ display:false } }, scales:{ x:{ min:0 } } }
});
</script>
@endif
</x-app-layout>
