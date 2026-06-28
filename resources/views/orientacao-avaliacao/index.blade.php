<x-app-layout>
    <div class="container-fluid">

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1" style="color:#00303A;">IEO — Índice de Excelência da Orientação</h1>
                <p class="text-muted mb-0 small">Resultados agregados por orientador. Mínimo de 3 avaliações para exibição.</p>
            </div>
            <div class="text-muted small">
                Total de respostas: <strong>{{ $avaliacoes->count() }}</strong>
            </div>
        </div>

        @if ($porOrientador->isEmpty())
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-bar-chart-line fs-1 text-muted mb-3 d-block"></i>
                    <p class="text-muted mb-0">Nenhuma avaliação registrada ainda.</p>
                </div>
            </div>
        @else
            <div class="row g-3 mb-4">
                @foreach ($porOrientador->filter(fn($r) => $r['total'] >= 3) as $registro)
                    @php
                        $ieo = $registro['ieo_medio'];
                        $cor = match(true) {
                            $ieo >= 90 => 'success',
                            $ieo >= 80 => 'primary',
                            $ieo >= 70 => 'info',
                            $ieo >= 60 => 'warning',
                            default    => 'danger',
                        };
                        $class = match(true) {
                            $ieo >= 90 => 'Excelente',
                            $ieo >= 80 => 'Muito bom',
                            $ieo >= 70 => 'Bom',
                            $ieo >= 60 => 'Regular',
                            default    => 'Crítico',
                        };
                    @endphp
                    <div class="col-md-6 col-xl-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h6 class="fw-bold mb-0">{{ $registro['nome'] }}</h6>
                                        <span class="text-muted small">{{ $registro['total'] }} avaliação(ões)</span>
                                    </div>
                                    <div class="text-end">
                                        <div class="fs-3 fw-bold text-{{ $cor }}">{{ number_format($ieo, 1) }}</div>
                                        <span class="badge text-bg-{{ $cor }}">{{ $class }}</span>
                                    </div>
                                </div>
                                <div class="small text-muted">
                                    @foreach ([
                                        'cat1_medio' => 'Competência científica',
                                        'cat2_medio' => 'Disponibilidade',
                                        'cat3_medio' => 'Contribuição ao trabalho',
                                        'cat4_medio' => 'Comunicação',
                                        'cat5_medio' => 'Suporte ao desenvolvimento',
                                        'cat6_medio' => 'Ética',
                                    ] as $campo => $label)
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>{{ $label }}</span>
                                            <span class="fw-semibold {{ $registro[$campo] < 75 ? 'text-danger' : '' }}">
                                                {{ number_format($registro[$campo], 1) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($porOrientador->filter(fn($r) => $r['total'] < 3)->isNotEmpty())
                <div class="alert alert-warning small">
                    <i class="bi bi-eye-slash me-2"></i>
                    {{ $porOrientador->filter(fn($r) => $r['total'] < 3)->count() }} orientador(es) com menos de 3 avaliações —
                    resultados ocultados para garantir confidencialidade.
                </div>
            @endif

            {{-- Linha do programa --}}
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;">
                        <i class="bi bi-graph-up-arrow me-2"></i>Resumo do programa
                    </h6>
                </div>
                <div class="card-body">
                    @php
                        $ieoGeral = $avaliacoes->avg('ieo_final');
                        $meta = 90;
                    @endphp
                    <div class="row g-3">
                        <div class="col-sm-4 text-center">
                            <div class="fs-2 fw-bold" style="color:#00303A;">
                                {{ $ieoGeral !== null ? number_format($ieoGeral, 1) : '—' }}
                            </div>
                            <div class="text-muted small">IEO médio do programa</div>
                        </div>
                        <div class="col-sm-4 text-center">
                            <div class="fs-2 fw-bold {{ $ieoGeral >= $meta ? 'text-success' : 'text-warning' }}">
                                {{ $meta }}
                            </div>
                            <div class="text-muted small">Meta do programa</div>
                        </div>
                        <div class="col-sm-4 text-center">
                            <div class="fs-2 fw-bold">{{ $avaliacoes->count() }}</div>
                            <div class="text-muted small">Respostas totais</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-app-layout>
