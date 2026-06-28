<x-app-layout>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-1"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="{{ route('relatorios.index') }}">Relatórios</a></li><li class="breadcrumb-item active">Dashboard Executivo</li></ol></nav>
    <h1 class="h3 fw-bold mb-3" style="color:#00303A;">Dashboard Executivo</h1>

    @include('relatorios._filtro')

    {{-- Análise 38: KPIs principais --}}
    <div class="row g-3 mb-4">
        @php
        $kpiCards = [
            ['label'=>'Bancas finalizadas','value'=>$kpis['totalBancas'],'unit'=>'','icon'=>'bi-mortarboard-fill','color'=>'#00303A','meta'=>null],
            ['label'=>'Taxa de aprovação','value'=>$kpis['taxaAprovacaoPct'],'unit'=>'%','icon'=>'bi-check-circle-fill','color'=>'success','meta'=>'Meta: 85%'],
            ['label'=>'Nota média','value'=>$kpis['notaMedia'],'unit'=>'/10','icon'=>'bi-star-fill','color'=>($kpis['notaMedia']??0)>=7?'success':'warning','meta'=>'Meta: 7,0'],
            ['label'=>'Índice de composição','value'=>$kpis['indiceComposicao'],'unit'=>'','icon'=>'bi-shield-check','color'=>($kpis['indiceComposicao']??0)>=0.75?'success':'warning','meta'=>'Meta: 0,75'],
            ['label'=>'Conformidade documental','value'=>$kpis['conformidade'],'unit'=>'%','icon'=>'bi-file-earmark-check-fill','color'=>($kpis['conformidade']??0)>=80?'success':'warning','meta'=>'Meta: 80%'],
            ['label'=>'IEO médio do programa','value'=>$kpis['ieoMedio'],'unit'=>'','icon'=>'bi-award-fill','color'=>($kpis['ieoMedio']??0)>=90?'success':(($kpis['ieoMedio']??0)>=70?'warning':'danger'),'meta'=>'Meta: 90'],
            ['label'=>'Tempo médio até defesa','value'=>$kpis['mesesDefesaMedia'] ? round($kpis['mesesDefesaMedia'],1) : null,'unit'=>'m','icon'=>'bi-clock-history','color'=>($kpis['mesesDefesaMedia']??99)<=24?'success':'warning','meta'=>'Meta: ≤24m'],
        ];
        @endphp
        @foreach ($kpiCards as $card)
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3">
                <div class="d-flex align-items-start gap-3">
                    <div class="fs-3 text-{{ $card['color'] }}"><i class="bi {{ $card['icon'] }}"></i></div>
                    <div>
                        <div class="fs-4 fw-bold text-{{ $card['color'] }}">
                            {{ $card['value'] !== null ? $card['value'].$card['unit'] : '—' }}
                        </div>
                        <div class="text-muted small">{{ $card['label'] }}</div>
                        @if ($card['meta'])
                        <div class="text-muted" style="font-size:.72rem;">{{ $card['meta'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Análise 40: Alertas operacionais --}}
    @if (!empty($alertas))
    <div class="card border-0 shadow-sm mb-3 border-start border-warning border-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Alertas Operacionais</h6>
        </div>
        <div class="card-body">
            <ul class="list-unstyled mb-0">
                @foreach ($alertas as $alerta)
                <li class="d-flex align-items-start gap-2 mb-2">
                    <i class="bi bi-circle-fill text-{{ $alerta['tipo'] ?? 'warning' }} mt-1" style="font-size:.5rem;flex-shrink:0;"></i>
                    <span class="small">{{ $alerta['mensagem'] }}</span>
                </li>
                @endforeach
            </ul>
        </div>
    </div>
    @else
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i>
        <span>Nenhum alerta operacional no momento.</span>
    </div>
    @endif

    {{-- Links rápidos para relatórios completos --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-grid-fill me-2"></i>Acesso Rápido aos Relatórios</h6>
        </div>
        <div class="card-body">
            <div class="row g-2">
                @php
                $links = [
                    ['route'=>'relatorios.bancas','label'=>'Desempenho das Bancas','icon'=>'bi-bar-chart-fill'],
                    ['route'=>'relatorios.composicao','label'=>'Composição B1–B8','icon'=>'bi-shield-check'],
                    ['route'=>'relatorios.docentes','label'=>'Bibliometria Docentes','icon'=>'bi-person-badge'],
                    ['route'=>'relatorios.percurso','label'=>'Percurso do Discente','icon'=>'bi-clock-history'],
                    ['route'=>'relatorios.orientacao','label'=>'IEO','icon'=>'bi-award-fill'],
                    ['route'=>'relatorios.impacto','label'=>'Impacto Dissertações','icon'=>'bi-lightning-charge-fill'],
                    ['route'=>'relatorios.discentes','label'=>'Bibliometria Discentes','icon'=>'bi-book-fill'],
                    ['route'=>'relatorios.documentos','label'=>'Conformidade Documental','icon'=>'bi-file-earmark-check-fill'],
                    ['route'=>'relatorios.autoavaliacao','label'=>'Autoavaliação','icon'=>'bi-clipboard2-data-fill'],
                    ['route'=>'relatorios.mabac','label'=>'MABAC','icon'=>'bi-trophy-fill'],
                    ['route'=>'relatorios.swara','label'=>'SWARA','icon'=>'bi-sliders'],
                    ['route'=>'relatorios.corpo-docente','label'=>'Corpo Docente','icon'=>'bi-people-fill'],
                ];
                @endphp
                @foreach ($links as $link)
                <div class="col-sm-4 col-md-3">
                    <a href="{{ route($link['route']) }}" class="btn btn-outline-secondary btn-sm w-100 text-start">
                        <i class="bi {{ $link['icon'] }} me-1"></i>{{ $link['label'] }}
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
</x-app-layout>
