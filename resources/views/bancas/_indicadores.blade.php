@php
    $criterios = [
        'b1' => 'Todos os membros têm ORCID válido',
        'b2' => 'Há avaliador externo à UNEB',
        'b3' => 'Mais de uma instituição representada',
        'b4' => 'Produção pública relacionada ao tema',
        'b5' => 'Diversidade de área de formação',
        'b6' => 'Sem repetição excessiva do mesmo avaliador',
        'b7' => 'Banca respeita critério interno/externo',
        'b8' => 'Aderência dos avaliadores à linha de pesquisa',
    ];

    $indice = $indicadores->indice_banca;

    [$classeIndice, $labelIndice] = match (true) {
        $indice >= 0.80 => ['success', 'Composição muito adequada'],
        $indice >= 0.60 => ['info',    'Composição adequada'],
        $indice >= 0.40 => ['warning', 'Ponto de atenção'],
        default         => ['danger',  'Composição frágil'],
    };
@endphp

<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6 class="fw-bold mb-0" style="color:#00303A;">
                    <i class="bi bi-bar-chart-line-fill me-2"></i>Índice de Composição da Banca
                </h6>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-{{ $classeIndice }} fs-6 px-3 py-2">
                        {{ number_format($indice, 2, ',', '') }}
                    </span>
                    <span class="badge bg-{{ $classeIndice }} px-3 py-2">{{ $labelIndice }}</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    @foreach ($criterios as $campo => $descricao)
                        @php $atingido = (bool) $indicadores->{$campo}; @endphp
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-2 p-2 rounded
                                {{ $atingido ? 'bg-success-subtle' : 'bg-danger-subtle' }}">
                                <i class="bi bi-{{ $atingido ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' }} flex-shrink-0"></i>
                                <span class="small">
                                    <span class="fw-semibold text-muted me-1">{{ strtoupper($campo) }}</span>{{ $descricao }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="text-muted small mt-3 mb-0">
                    Calculado em {{ $indicadores->calculado_em?->format('d/m/Y \à\s H:i') }}.
                    Indicador interno de autoavaliação — não é exibido ao discente.
                </p>
            </div>
        </div>
    </div>
</div>
