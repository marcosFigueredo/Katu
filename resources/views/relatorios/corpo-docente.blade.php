<x-app-layout>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-1"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="{{ route('relatorios.index') }}">Relatórios</a></li><li class="breadcrumb-item active">Corpo Docente</li></ol></nav>
    <h1 class="h3 fw-bold mb-3" style="color:#00303A;">Corpo Docente</h1>

    @include('relatorios._filtro')

    {{-- Análise 35: Participação como avaliador --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-clipboard2-check-fill me-2"></i>Participação como Avaliador</h6>
        </div>
        <div class="card-body p-0">
            @if ($participacao->isEmpty())
                <p class="text-muted small p-3 mb-0">Nenhuma participação no período.</p>
            @else
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">#</th><th>Docente</th><th class="text-center">Total</th><th class="text-center">Qualificações</th><th class="text-center">Defesas</th></tr></thead>
                    <tbody>
                        @foreach ($participacao as $i => $d)
                        <tr>
                            <td class="ps-3 text-muted small">{{ $i+1 }}</td>
                            <td class="fw-semibold">{{ $d['nome'] ?? '—' }}</td>
                            <td class="text-center fw-bold">{{ $d['total'] }}</td>
                            <td class="text-center">{{ $d['qualif'] }}</td>
                            <td class="text-center">{{ $d['defesa'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        {{-- Análise 36: Diversidade institucional --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-bank2 me-2"></i>Diversidade Institucional</h6>
                </div>
                <div class="card-body">
                    @if ($diversidadeInst->isEmpty())
                        <p class="text-muted small mb-0">Sem dados.</p>
                    @else
                    <canvas id="chartInst" height="180"></canvas>
                    @endif
                </div>
            </div>
        </div>

        {{-- Análise 37: Diversidade de área --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-mortarboard-fill me-2"></i>Diversidade de Área de Formação</h6>
                </div>
                <div class="card-body">
                    @if ($diversidadeArea->isEmpty())
                        <p class="text-muted small mb-0">Sem dados.</p>
                    @else
                    <canvas id="chartArea" height="180"></canvas>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if ($diversidadeInst->isNotEmpty() || $diversidadeArea->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
@php
$palette = ['#00303A','#2d6a73','#5aaab5','#a8d8de','#f4c05a','#e07b39','#c03a2b','#8bc34a','#9c27b0'];
@endphp
@if ($diversidadeInst->isNotEmpty())
new Chart(document.getElementById('chartInst'), {
    type: 'doughnut',
    data: { labels: @json($diversidadeInst->keys()), datasets: [{ data: @json($diversidadeInst->values()), backgroundColor: @json($palette) }] },
    options: { plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } } }
});
@endif
@if ($diversidadeArea->isNotEmpty())
new Chart(document.getElementById('chartArea'), {
    type: 'doughnut',
    data: { labels: @json($diversidadeArea->keys()), datasets: [{ data: @json($diversidadeArea->values()), backgroundColor: @json($palette) }] },
    options: { plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } } }
});
@endif
</script>
@endif
</x-app-layout>
