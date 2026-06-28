<x-app-layout>
    <div class="container-fluid">

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('baremas.index') }}">Baremas</a></li>
                        <li class="breadcrumb-item active">SWARA — {{ $barema->nome }}</li>
                    </ol>
                </nav>
                <h1 class="h3 fw-bold mb-1" style="color: #00303A;">Calibração SWARA</h1>
                <p class="text-muted mb-0">Sessões de calibração de pesos para <strong>{{ $barema->nome }}</strong>.</p>
            </div>
            <a href="{{ route('swara.create', $barema) }}" class="btn btn-primary fw-semibold">
                <i class="bi bi-plus-lg me-1"></i> Nova sessão
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($sessoes->isEmpty())
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-sliders fs-1 text-muted mb-3 d-block"></i>
                    <p class="text-muted mb-1">Nenhuma sessão SWARA registrada para este barema.</p>
                    <a href="{{ route('swara.create', $barema) }}" class="btn btn-primary mt-2">
                        <i class="bi bi-plus-lg me-1"></i> Criar primeira sessão
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
                                    <th>Aprovado por</th>
                                    <th>Critérios</th>
                                    <th>Criada em</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sessoes as $sessao)
                                    <tr>
                                        <td class="ps-4 fw-semibold">{{ $sessao->nome }}</td>
                                        <td class="text-muted">{{ $sessao->aprovado_por ?? '—' }}</td>
                                        <td>{{ $sessao->julgamentos->count() }}</td>
                                        <td>{{ $sessao->created_at->format('d/m/Y') }}</td>
                                        <td>
                                            @if ($sessao->foiAplicada())
                                                <span class="badge text-bg-success">Aplicada em {{ $sessao->aplicado_em->format('d/m/Y') }}</span>
                                            @else
                                                <span class="badge text-bg-secondary">Não aplicada</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="{{ route('swara.show', [$barema, $sessao]) }}" class="btn btn-sm btn-outline-primary">
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
