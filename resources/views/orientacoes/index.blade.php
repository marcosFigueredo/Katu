<x-app-layout>
    <div class="container-fluid">

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1" style="color: #00303A;">Orientações</h1>
                <p class="text-muted mb-0">
                    Vínculo entre discentes, orientadores e coorientadores do programa.
                </p>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($trabalhos->isEmpty())
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-arrow-left-right fs-1 text-muted mb-3 d-block"></i>
                    <p class="text-muted mb-0">Nenhum trabalho acadêmico cadastrado ainda.</p>
                    <p class="text-muted small">Registre discentes e seus vínculos de programa para poder gerenciar orientações.</p>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Discente</th>
                                    <th>Nível / Linha</th>
                                    <th>Orientador</th>
                                    <th>Coorientador(es)</th>
                                    <th class="text-center">Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($trabalhos as $trabalho)
                                    @php
                                        $discente    = $trabalho->discenteVinculo?->discenteProfile?->user;
                                        $vinculo     = $trabalho->discenteVinculo;
                                        $orientador  = $trabalho->orientacoes->firstWhere('tipo', 'orientador');
                                        $coOrientadores = $trabalho->orientacoes->where('tipo', 'coorientador');
                                        $temOrientador  = (bool) $orientador;
                                    @endphp
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-semibold text-dark">
                                                {{ $discente?->name ?? '—' }}
                                            </div>
                                            <div class="text-muted small">
                                                {{ $vinculo?->matricula ?? '' }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-dark">
                                                {{ match($vinculo?->nivel) {
                                                    'mestrado'   => 'Mestrado',
                                                    'doutorado'  => 'Doutorado',
                                                    default      => $vinculo?->nivel ?? '—',
                                                } }}
                                            </div>
                                            <div class="text-muted small">
                                                {{ $vinculo?->linhaPesquisa?->nome ?? '—' }}
                                            </div>
                                        </td>
                                        <td>
                                            @if ($orientador)
                                                <div class="fw-semibold text-dark">
                                                    {{ $orientador->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '—' }}
                                                </div>
                                            @else
                                                <span class="badge text-bg-warning">Não atribuído</span>
                                            @endif
                                        </td>
                                        <td>
                                            @forelse ($coOrientadores as $co)
                                                <div class="small text-muted">
                                                    {{ $co->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '—' }}
                                                </div>
                                            @empty
                                                <span class="text-muted small">—</span>
                                            @endforelse
                                        </td>
                                        <td class="text-center">
                                            @if ($temOrientador)
                                                <span class="badge text-bg-success">Atribuído</span>
                                            @else
                                                <span class="badge text-bg-secondary">Pendente</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="{{ route('orientacoes.edit', $trabalho) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil-fill me-1"></i>Gerenciar
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
