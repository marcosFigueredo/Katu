<x-app-layout>
    <div class="container-fluid">

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1" style="color: #00303A;">Autoavaliação</h1>
                <p class="text-muted mb-0">Indicadores institucionais por período.</p>
            </div>
            <a href="{{ route('autoavaliacao.create') }}" class="btn btn-primary fw-semibold">
                <i class="bi bi-plus-lg me-1"></i> Novo período
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($periodos->isEmpty())
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-clipboard2-data fs-1 text-muted mb-3 d-block"></i>
                    <p class="text-muted mb-1">Nenhum período de autoavaliação cadastrado.</p>
                    <a href="{{ route('autoavaliacao.create') }}" class="btn btn-primary mt-2">
                        <i class="bi bi-plus-lg me-1"></i> Criar primeiro período
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
                                    <th>Ano/Semestre</th>
                                    <th>Período</th>
                                    <th class="text-center">Indicadores</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($periodos as $periodo)
                                    <tr>
                                        <td class="ps-4 fw-semibold">{{ $periodo->nome }}</td>
                                        <td>{{ $periodo->ano }}{{ $periodo->semestre ? '/S'.$periodo->semestre : '' }}</td>
                                        <td class="small text-muted">
                                            {{ $periodo->data_inicio->format('d/m/Y') }}
                                            a {{ $periodo->data_fim->format('d/m/Y') }}
                                        </td>
                                        <td class="text-center">{{ $periodo->indicadores_count }}</td>
                                        <td>
                                            <span class="badge text-bg-{{ $periodo->status === 'concluido' ? 'success' : 'warning' }}">
                                                {{ $periodo->status === 'concluido' ? 'Concluído' : 'Em andamento' }}
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="{{ route('autoavaliacao.show', $periodo) }}" class="btn btn-sm btn-outline-primary">
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
