<x-app-layout>
    <div class="container-fluid">

        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('orientacoes.index') }}">Orientações</a></li>
                <li class="breadcrumb-item active">Bibliometria — {{ $trabalho->discenteVinculo?->discenteProfile?->user?->name }}</li>
            </ol>
        </nav>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="d-flex align-items-start justify-content-between mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Indicadores Bibliométricos</h1>
                <p class="text-muted mb-0">
                    {{ Str::limit($trabalho->titulo, 80) }} ·
                    {{ $trabalho->discenteVinculo?->discenteProfile?->user?->name }}
                </p>
            </div>
            <form method="POST" action="{{ route('bibliometria.sincronizar', $trabalho) }}">
                @csrf
                <button class="btn btn-primary fw-semibold">
                    <i class="bi bi-arrow-repeat me-1"></i> Atualizar via OpenAlex/Crossref
                </button>
            </form>
        </div>

        @if (!$indicador)
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                Nenhum indicador bibliométrico coletado ainda. Clique em <strong>Atualizar</strong> para consultar OpenAlex e Crossref.
            </div>
        @else
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm text-center p-3">
                        <div class="fs-1 fw-bold" style="color:#00303A;">{{ $indicador->total_publicacoes_discente }}</div>
                        <div class="text-muted small">Publicações do discente</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm text-center p-3">
                        <div class="fs-1 fw-bold" style="color:#00303A;">{{ $indicador->total_com_doi }}</div>
                        <div class="text-muted small">Com DOI</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm text-center p-3">
                        <div class="fs-1 fw-bold" style="color:#00303A;">{{ $indicador->total_citacoes_crossref }}</div>
                        <div class="text-muted small">Citações (Crossref)</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm p-3">
                        <ul class="list-unstyled mb-0 small">
                            <li class="mb-1">
                                <i class="bi bi-{{ $indicador->coautoria_orientador ? 'check-circle-fill text-success' : 'x-circle text-muted' }} me-2"></i>
                                Coautoria com orientador
                            </li>
                            <li class="mb-1">
                                <i class="bi bi-{{ $indicador->coautoria_internacional ? 'check-circle-fill text-success' : 'x-circle text-muted' }} me-2"></i>
                                Coautoria internacional
                            </li>
                            <li>
                                <i class="bi bi-{{ $indicador->presenca_bases_indexadas ? 'check-circle-fill text-success' : 'x-circle text-muted' }} me-2"></i>
                                Presença em bases indexadas
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            @if ($indicador->dados_json && count($indicador->dados_json) > 0)
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0" style="color:#00303A;">
                            <i class="bi bi-journals me-2"></i>Publicações (OpenAlex)
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Título</th>
                                        <th>Ano</th>
                                        <th>Tipo</th>
                                        <th>Veículo</th>
                                        <th class="text-center">Citações</th>
                                        <th class="text-center">DOI</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($indicador->dados_json as $pub)
                                        <tr>
                                            <td class="ps-4 small fw-semibold" style="max-width:300px">
                                                {{ Str::limit($pub['titulo'] ?? '—', 80) }}
                                            </td>
                                            <td class="small">{{ $pub['ano'] ?? '—' }}</td>
                                            <td class="small text-muted">{{ $pub['tipo'] ?? '—' }}</td>
                                            <td class="small text-muted">{{ Str::limit($pub['host_venue'] ?? '—', 40) }}</td>
                                            <td class="text-center small">{{ $pub['citacoes'] ?? 0 }}</td>
                                            <td class="text-center">
                                                @if (!empty($pub['doi']))
                                                    <a href="https://doi.org/{{ $pub['doi'] }}" target="_blank"
                                                       class="badge text-bg-info text-decoration-none">DOI</a>
                                                @else
                                                    <span class="text-muted small">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white text-muted small py-2 px-4">
                        Consultado em {{ $indicador->consultado_em?->format('d/m/Y H:i') }}.
                        Fonte: OpenAlex · Crossref. Indicadores para autoavaliação interna — não ranquear docentes.
                    </div>
                </div>
            @endif
        @endif

    </div>
</x-app-layout>
