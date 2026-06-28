<x-app-layout>
<div class="container-fluid">
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Relatórios — Avalia Katu</h1>
        <p class="text-muted mb-0">Selecione um módulo de análise abaixo.</p>
    </div>

    @php
    $blocos = [
        ['rota'=>'relatorios.bancas',        'icone'=>'bi-person-workspace',    'titulo'=>'Desempenho das Bancas',          'desc'=>'Resultados, notas, variância e evolução temporal (análises 1–7)'],
        ['rota'=>'relatorios.composicao',     'icone'=>'bi-shield-check',        'titulo'=>'Composição das Bancas (B1–B8)',   'desc'=>'Indicadores de qualidade de composição e correlações (análises 8–10)'],
        ['rota'=>'relatorios.docentes',       'icone'=>'bi-person-badge-fill',   'titulo'=>'Bibliometria dos Docentes',       'desc'=>'ORCID/OpenAlex — orientadores vs avaliadores (análises 11–15)'],
        ['rota'=>'relatorios.percurso',       'icone'=>'bi-hourglass-split',     'titulo'=>'Percurso do Discente',            'desc'=>'Tempo até qualificação/defesa, funil, cohort (análises 16–19)'],
        ['rota'=>'relatorios.orientacao',     'icone'=>'bi-person-heart',        'titulo'=>'Avaliação da Orientação (IEO)',   'desc'=>'Índice de Excelência da Orientação por orientador (análises 20–22)'],
        ['rota'=>'relatorios.impacto',        'icone'=>'bi-lightbulb-fill',      'titulo'=>'Impacto das Dissertações',        'desc'=>'Dimensões de impacto declaradas pelos discentes (análises 23–24)'],
        ['rota'=>'relatorios.discentes',      'icone'=>'bi-journal-richtext',    'titulo'=>'Bibliometria dos Discentes',      'desc'=>'Publicações, DOI, coautoria, correlação com nota (análises 25–26)'],
        ['rota'=>'relatorios.documentos',     'icone'=>'bi-file-earmark-check',  'titulo'=>'Conformidade Documental',         'desc'=>'Conformidade, itens pendentes, LaTeX, PDFs (análises 27–28)'],
        ['rota'=>'relatorios.autoavaliacao',  'icone'=>'bi-clipboard2-data',     'titulo'=>'Autoavaliação Institucional',     'desc'=>'Indicadores A01–A16, histórico, por linha (análises 29–31)'],
        ['rota'=>'relatorios.mabac',          'icone'=>'bi-bar-chart-steps',     'titulo'=>'Análise MABAC',                   'desc'=>'Rankings multicritério por análise (análises 32–33)'],
        ['rota'=>'relatorios.swara',          'icone'=>'bi-sliders',             'titulo'=>'Pesos SWARA',                     'desc'=>'Pesos calibrados dos critérios por barema (análise 34)'],
        ['rota'=>'relatorios.corpo-docente',  'icone'=>'bi-people-fill',         'titulo'=>'Corpo Docente',                   'desc'=>'Participação, diversidade institucional e de formação (análises 35–37)'],
        ['rota'=>'relatorios.dashboard',      'icone'=>'bi-speedometer2',        'titulo'=>'Dashboard Executivo + Alertas',   'desc'=>'KPIs do programa, alertas operacionais e exportação (análises 38–40)'],
    ];
    @endphp

    <div class="row g-3">
        @foreach ($blocos as $i => $b)
        <div class="col-md-6 col-xl-4">
            <a href="{{ route($b['rota']) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 card-hover">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:44px;height:44px;background:#e8f4f0;">
                                <i class="bi {{ $b['icone'] }} fs-4" style="color:#00303A;"></i>
                            </div>
                            <div>
                                <div class="fw-bold" style="color:#00303A;">{{ $b['titulo'] }}</div>
                                <div class="text-muted" style="font-size:.78rem;">
                                    Análises {{ $i*3+1 }}–{{ $i*3+3 }}
                                </div>
                            </div>
                        </div>
                        <p class="text-muted small mb-0">{{ $b['desc'] }}</p>
                    </div>
                </div>
            </a>
        </div>
        @endforeach
    </div>
</div>
<style>
.card-hover { transition: box-shadow .15s, transform .15s; }
.card-hover:hover { box-shadow: 0 6px 20px rgba(0,48,58,.15) !important; transform: translateY(-2px); }
</style>
</x-app-layout>
