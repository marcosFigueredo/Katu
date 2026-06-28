<x-app-layout>
    <div class="container-fluid">

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1" style="color: #00303A;">Análise MABAC</h1>
                <p class="text-muted mb-0">Análise multicritério comparativa de trabalhos avaliados.</p>
            </div>
            <a href="{{ route('mabac.create') }}" class="btn btn-primary fw-semibold">
                <i class="bi bi-plus-lg me-1"></i> Nova análise
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($analises->isEmpty())
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-bar-chart-steps fs-1 text-muted mb-3 d-block"></i>
                    <p class="text-muted mb-1">Nenhuma análise MABAC gerada ainda.</p>
                    <a href="{{ route('mabac.create') }}" class="btn btn-primary mt-2">
                        <i class="bi bi-plus-lg me-1"></i> Criar primeira análise
                    </a>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Nome</th>
                                    <th>Barema</th>
                                    <th>Tipo</th>
                                    <th>Período</th>
                                    <th class="text-center">Trabalhos</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($analises as $analise)
                                    <tr>
                                        <td class="ps-4 fw-semibold">{{ $analise->nome }}</td>
                                        <td class="text-muted small">{{ $analise->barema->nome }}</td>
                                        <td>
                                            <span class="badge text-bg-secondary">
                                                {{ match($analise->tipo_banca) {
                                                    'qualificacao' => 'Qualificação',
                                                    'defesa'       => 'Defesa',
                                                    default        => 'Ambos',
                                                } }}
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            {{ $analise->periodo_inicio->format('d/m/Y') }}
                                            a {{ $analise->periodo_fim->format('d/m/Y') }}
                                        </td>
                                        <td class="text-center">{{ $analise->resultados_count }}</td>
                                        <td class="text-end pe-4">
                                            <a href="{{ route('mabac.show', $analise) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye me-1"></i> Ver
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-app-layout>
