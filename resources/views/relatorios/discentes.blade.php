<x-app-layout>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-1"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="{{ route('relatorios.index') }}">Relatórios</a></li><li class="breadcrumb-item active">Bibliometria dos Discentes</li></ol></nav>
    <h1 class="h3 fw-bold mb-3" style="color:#00303A;">Bibliometria dos Discentes</h1>

    @if ($tabelaDiscentes->isEmpty())
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Nenhum indicador de discente registrado ainda.</div>
    @else

    {{-- Análise 25: KPIs de cobertura --}}
    <div class="row g-3 mb-4">
        @php $tot = max($resumo['total'], 1); @endphp
        <div class="col-sm-2 text-center"><div class="card border-0 shadow-sm p-3">
            <div class="fs-2 fw-bold" style="color:#00303A;">{{ $resumo['total'] }}</div>
            <div class="text-muted small">Trabalhos</div>
        </div></div>
        <div class="col-sm-2 text-center"><div class="card border-0 shadow-sm p-3">
            <div class="fs-2 fw-bold text-success">{{ $resumo['com_publicacoes'] }}</div>
            <div class="text-muted small">Com publicações</div>
            <div class="text-muted" style="font-size:.72rem;">{{ round($resumo['com_publicacoes']/$tot*100) }}%</div>
        </div></div>
        <div class="col-sm-2 text-center"><div class="card border-0 shadow-sm p-3">
            <div class="fs-2 fw-bold text-primary">{{ $resumo['com_doi'] }}</div>
            <div class="text-muted small">Com DOI</div>
            <div class="text-muted" style="font-size:.72rem;">{{ round($resumo['com_doi']/$tot*100) }}%</div>
        </div></div>
        <div class="col-sm-2 text-center"><div class="card border-0 shadow-sm p-3">
            <div class="fs-2 fw-bold text-info">{{ $resumo['com_coautoria_or'] }}</div>
            <div class="text-muted small">Coautoria c/ orientador</div>
            <div class="text-muted" style="font-size:.72rem;">{{ round($resumo['com_coautoria_or']/$tot*100) }}%</div>
        </div></div>
        <div class="col-sm-2 text-center"><div class="card border-0 shadow-sm p-3">
            <div class="fs-2 fw-bold text-warning">{{ $resumo['com_coautoria_int'] }}</div>
            <div class="text-muted small">Coautoria internacional</div>
            <div class="text-muted" style="font-size:.72rem;">{{ round($resumo['com_coautoria_int']/$tot*100) }}%</div>
        </div></div>
        <div class="col-sm-2 text-center"><div class="card border-0 shadow-sm p-3">
            <div class="fs-2 fw-bold text-secondary">{{ $resumo['em_bases'] }}</div>
            <div class="text-muted small">Em bases indexadas</div>
            <div class="text-muted" style="font-size:.72rem;">{{ round($resumo['em_bases']/$tot*100) }}%</div>
        </div></div>
    </div>

    <div class="row g-3 mb-3">
        {{-- Tabela de discentes --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-list-check me-2"></i>Ranking Bibliométrico dos Discentes</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th class="ps-3">Nome</th><th>Linha</th><th class="text-center">Pub.</th><th class="text-center">DOI</th><th class="text-center">Cit.</th><th class="text-center">Co/Or</th><th class="text-center">Co/Int</th><th class="text-center">Bases</th><th class="text-center">Nota</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($tabelaDiscentes as $r)
                                <tr>
                                    <td class="ps-3 fw-semibold small">{{ $r['nome'] }}</td>
                                    <td class="small text-muted">{{ $r['linha'] }}</td>
                                    <td class="text-center">{{ $r['publicacoes'] }}</td>
                                    <td class="text-center">{{ $r['com_doi'] }}</td>
                                    <td class="text-center">{{ $r['citacoes'] }}</td>
                                    <td class="text-center"><i class="bi bi-{{ $r['coautoria_or'] ? 'check-circle-fill text-success' : 'x-circle text-secondary' }}"></i></td>
                                    <td class="text-center"><i class="bi bi-{{ $r['coautoria_int'] ? 'check-circle-fill text-success' : 'x-circle text-secondary' }}"></i></td>
                                    <td class="text-center"><i class="bi bi-{{ $r['bases_indexadas'] ? 'check-circle-fill text-success' : 'x-circle text-secondary' }}"></i></td>
                                    <td class="text-center small">{{ $r['nota_banca'] ? number_format($r['nota_banca'],1) : '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Análise 26: Scatter publicações × nota --}}
        @if ($scatter->isNotEmpty())
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-scatter-chart me-2"></i>Publicações × Nota da Banca</h6>
                </div>
                <div class="card-body d-flex align-items-center">
                    <canvas id="chartScatter"></canvas>
                </div>
                <div class="card-footer bg-white text-muted small">Cada ponto = um discente. Eixo X = publicações, Y = nota média.</div>
            </div>
        </div>
        @endif
    </div>

    @endif
</div>

@if (!$tabelaDiscentes->isEmpty() && $scatter->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartScatter'), {
    type: 'scatter',
    data: { datasets: [{ label:'Discentes', data: @json($scatter), backgroundColor:'rgba(0,48,58,.6)', pointRadius:5 }] },
    options: { scales: {
        x: { title:{ display:true, text:'Publicações' }, min:0 },
        y: { title:{ display:true, text:'Nota banca' }, min:0, max:10 }
    }, plugins:{ legend:{ display:false } } }
});
</script>
@endif
</x-app-layout>
