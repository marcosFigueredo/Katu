<x-app-layout>
    <div class="container-fluid">

        <div class="d-flex align-items-start justify-content-between mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('autoavaliacao.index') }}">Autoavaliação</a></li>
                        <li class="breadcrumb-item active">{{ $autoavaliacao->nome }}</li>
                    </ol>
                </nav>
                <h1 class="h3 fw-bold mb-1" style="color:#00303A;">{{ $autoavaliacao->nome }}</h1>
                <p class="text-muted mb-0 small">
                    {{ $autoavaliacao->data_inicio->format('d/m/Y') }} a {{ $autoavaliacao->data_fim->format('d/m/Y') }}
                </p>
            </div>
            <div class="d-flex gap-2">
                <form method="POST" action="{{ route('autoavaliacao.calcular', $autoavaliacao) }}">
                    @csrf
                    <button class="btn btn-primary fw-semibold">
                        <i class="bi bi-arrow-repeat me-1"></i>
                        {{ $autoavaliacao->indicadores->isEmpty() ? 'Calcular indicadores' : 'Recalcular' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('autoavaliacao.destroy', $autoavaliacao) }}"
                      onsubmit="return confirm('Excluir este período e todos os indicadores?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($autoavaliacao->indicadores->isEmpty())
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                Nenhum indicador calculado. Clique em <strong>Calcular indicadores</strong> para processar o período.
            </div>
        @else
            {{-- Cards de destaque --}}
            @php
                $destaque = $autoavaliacao->indicadores->keyBy('codigo');
            @endphp
            <div class="row g-3 mb-4">
                @foreach (['A01' => 'Total de bancas', 'A04' => 'Nota média', 'A05' => 'Aprovação (%)', 'A09' => 'Índice médio B1–B8'] as $cod => $label)
                    @if ($destaque->has($cod))
                        <div class="col-sm-6 col-lg-3">
                            <div class="card border-0 shadow-sm text-center p-3">
                                <div class="fs-1 fw-bold" style="color:#00303A;">
                                    {{ $destaque[$cod]->valor_numerico !== null ? number_format($destaque[$cod]->valor_numerico, $cod === 'A09' ? 3 : 1) : '—' }}
                                </div>
                                <div class="text-muted small">{{ $label }}</div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- Tabela completa --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;">
                        <i class="bi bi-list-check me-2"></i>Todos os indicadores
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4" style="width:80px">Código</th>
                                    <th>Indicador</th>
                                    <th class="text-end pe-4">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($autoavaliacao->indicadores as $ind)
                                    <tr>
                                        <td class="ps-4">
                                            <span class="badge text-bg-secondary">{{ $ind->codigo }}</span>
                                        </td>
                                        <td>{{ $ind->nome }}</td>
                                        <td class="text-end pe-4 fw-semibold">
                                            @if ($ind->valor_numerico !== null)
                                                {{ number_format($ind->valor_numerico, 2) }}
                                            @elseif ($ind->valor_texto)
                                                @php $detalhes = json_decode($ind->valor_texto, true) @endphp
                                                @if (is_array($detalhes))
                                                    <button class="btn btn-sm btn-outline-secondary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modal-{{ $ind->codigo }}">
                                                        <i class="bi bi-table me-1"></i> Detalhar
                                                    </button>

                                                    {{-- Modal --}}
                                                    <div class="modal fade" id="modal-{{ $ind->codigo }}" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h6 class="modal-title fw-bold">{{ $ind->nome }}</h6>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body p-0">
                                                                    <table class="table table-sm mb-0">
                                                                        <thead class="table-light">
                                                                            <tr><th class="ps-3">Linha</th><th>Total</th><th>Aprovadas</th></tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            @foreach ($detalhes as $linha => $dados)
                                                                                <tr>
                                                                                    <td class="ps-3">{{ $linha }}</td>
                                                                                    <td>{{ $dados['total'] ?? 0 }}</td>
                                                                                    <td>{{ $dados['aprovadas'] ?? 0 }}</td>
                                                                                </tr>
                                                                            @endforeach
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    {{ Str::limit($ind->valor_texto, 60) }}
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white text-muted small py-2 px-4">
                    Calculado em {{ $autoavaliacao->indicadores->first()?->calculado_em?->format('d/m/Y H:i') }}.
                    Exclusivo para autoavaliação interna.
                </div>
            </div>
        @endif

    </div>
</x-app-layout>
