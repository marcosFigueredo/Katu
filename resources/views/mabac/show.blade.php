<x-app-layout>
    <div class="container-fluid">

        <div class="d-flex align-items-start justify-content-between mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('mabac.index') }}">MABAC</a></li>
                        <li class="breadcrumb-item active">{{ $mabac->nome }}</li>
                    </ol>
                </nav>
                <h1 class="h3 fw-bold mb-1" style="color:#00303A;">{{ $mabac->nome }}</h1>
                <p class="text-muted mb-0 small">
                    Barema: <strong>{{ $mabac->barema->nome }}</strong> ·
                    {{ $mabac->periodo_inicio->format('d/m/Y') }} a {{ $mabac->periodo_fim->format('d/m/Y') }} ·
                    {{ match($mabac->tipo_banca) { 'qualificacao' => 'Qualificação', 'defesa' => 'Defesa', default => 'Ambos' } }}
                    @if ($mabac->linhaPesquisa) · Linha: {{ $mabac->linhaPesquisa->nome }} @endif
                </p>
            </div>
            <form method="POST" action="{{ route('mabac.destroy', $mabac) }}"
                  onsubmit="return confirm('Excluir esta análise?')">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash me-1"></i> Excluir
                </button>
            </form>
        </div>

        @if ($mabac->resultados->isEmpty())
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                Nenhum trabalho encontrado para os filtros selecionados. Verifique se há bancas finalizadas no período com avaliações enviadas neste barema.
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0" style="color:#00303A;">
                        <i class="bi bi-trophy me-2"></i>Ranking MABAC — {{ $mabac->resultados->count() }} trabalhos
                    </h6>
                    <span class="text-muted small">S_i positivo = acima da fronteira · negativo = abaixo</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 text-center" style="width:60px">#</th>
                                    <th>Trabalho / Discente</th>
                                    <th>Linha</th>
                                    <th>Tipo banca</th>
                                    <th class="text-center">Escore S_i</th>
                                    <th>Desempenho</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($mabac->resultados as $resultado)
                                    @php
                                        $discente = $resultado->banca->trabalhoAcademico
                                            ?->discenteVinculo?->discenteProfile?->user?->name ?? '—';
                                        $titulo   = $resultado->banca->trabalhoAcademico?->titulo ?? '—';
                                        $linha    = $resultado->banca->trabalhoAcademico?->linhaPesquisa?->nome ?? '—';
                                        $s        = $resultado->escore_final;
                                        [$cor, $label] = match(true) {
                                            $s > 0.5  => ['success', 'Acima da fronteira'],
                                            $s > -0.5 => ['warning', 'Próximo à fronteira'],
                                            default   => ['danger',  'Abaixo da fronteira'],
                                        };
                                    @endphp
                                    <tr>
                                        <td class="ps-4 text-center">
                                            @if ($resultado->ranking === 1)
                                                <i class="bi bi-trophy-fill text-warning fs-5"></i>
                                            @else
                                                <span class="fw-bold text-muted">{{ $resultado->ranking }}º</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fw-semibold" style="max-width:320px">{{ Str::limit($titulo, 60) }}</div>
                                            <div class="text-muted small">{{ $discente }}</div>
                                        </td>
                                        <td class="small text-muted">{{ $linha }}</td>
                                        <td>
                                            <span class="badge text-bg-secondary">
                                                {{ $resultado->banca->tipo === 'defesa' ? 'Defesa' : 'Qualificação' }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="fw-bold {{ $s >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ number_format($s, 4) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge text-bg-{{ $cor }} bg-opacity-75">{{ $label }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-3 small">
                <i class="bi bi-info-circle-fill me-2"></i>
                <strong>Interpretação:</strong> O escore MABAC (S_i) compara cada trabalho com a fronteira de referência (média geométrica). Valores positivos indicam desempenho acima do padrão médio do grupo; valores negativos indicam desempenho abaixo. Este indicador é exclusivo para autoavaliação interna — não interfere na nota ou aprovação do discente.
            </div>
        @endif

    </div>
</x-app-layout>
