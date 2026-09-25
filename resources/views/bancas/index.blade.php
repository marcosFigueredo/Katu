<x-app-layout>
    <div class="container-fluid">

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1" style="color: #00303A;">Bancas</h1>
                <p class="text-muted mb-0">
                    Composição da banca com orientador, coorientadores em ata e avaliadores habilitados.
                </p>
            </div>
            <div class="d-flex gap-2"><a href="{{ route('bancas.homologacao') }}" class="btn btn-outline-warning fw-semibold"><i class="bi bi-check2-square me-1"></i>Homologação</a><a href="{{ route('bancas.create') }}" class="btn btn-primary fw-semibold">
                <i class="bi bi-plus-lg me-1"></i>Nova banca
            </a></div>
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
                    <i class="bi bi-person-workspace fs-1 text-muted mb-3 d-block"></i>
                    <p class="text-muted mb-0">Nenhum trabalho acadêmico cadastrado ainda.</p>
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
                                    <th>Presidência</th>
                                    <th>Bancas</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($trabalhos as $trabalho)
                                    @php
                                        $discente    = $trabalho->discenteVinculo?->discenteProfile?->user;
                                        $vinculo     = $trabalho->discenteVinculo;
                                        $orientador  = $trabalho->orientacoes->firstWhere('tipo', 'orientador');
                                        $bancaQualif = $trabalho->bancas->firstWhere('tipo', 'qualificacao');
                                        $bancaDefesa = $trabalho->bancas->firstWhere('tipo', 'defesa');
                                        $qualificou  = $bancaQualif?->status === 'finalizada';

                                        $statusBadge = fn ($status) => match($status) {
                                            'cadastrada'   => ['Agendada',     'secondary'],
                                            'em_avaliacao' => ['Em avaliação', 'warning'],
                                            'finalizada'   => ['Finalizada',   'success'],
                                            'cancelada'    => ['Cancelada',    'danger'],
                                            default        => ['—',            'light border'],
                                        };
                                    @endphp
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-semibold text-dark">{{ $discente?->name ?? '-' }}</div>
                                            <div class="text-muted small">{{ $vinculo?->matricula ?? '' }}</div>
                                        </td>
                                        <td>
                                            <div>{{ match($trabalho->nivel) {
                                                'mestrado'  => 'Mestrado',
                                                'doutorado' => 'Doutorado',
                                                default     => $trabalho->nivel ?? '-',
                                            } }}</div>
                                            <div class="text-muted small">{{ $vinculo?->linhaPesquisa?->nome ?? '-' }}</div>
                                        </td>
                                        <td>
                                            @if ($orientador)
                                                <div class="fw-semibold">
                                                    {{ $orientador->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '-' }}
                                                </div>
                                                <div class="text-muted small">Orientador, presidente da banca</div>
                                            @else
                                                <span class="badge text-bg-warning">Sem orientador</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($trabalho->bancas->isEmpty())
                                                <span class="text-muted small">Nenhuma banca cadastrada</span>
                                            @else
                                                @foreach ($trabalho->bancas->sortBy('tipo') as $banca)
                                                    @php
                                                        [$lbl, $cor] = $statusBadge($banca->status);
                                                        $tipoLabel   = match(true) {
                                                            str_starts_with($banca->tipo, 'qualificacao') => 'Qualificação',
                                                            str_starts_with($banca->tipo, 'defesa')       => 'Defesa',
                                                            default                                        => $banca->tipo,
                                                        };
                                                    @endphp
                                                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                                        <span class="text-dark small fw-semibold">{{ $tipoLabel }}</span>
                                                        <span class="badge text-bg-{{ $cor }}">{{ $lbl }}</span>
                                                        @if ($banca->status === 'finalizada' && $banca->resultado)
                                                            @php
                                                                $resCor = match($banca->resultado) {
                                                                    'aprovado'              => 'success',
                                                                    'aprovado_modificacoes' => 'warning',
                                                                    'reprovado'             => 'danger',
                                                                    default                 => 'secondary',
                                                                };
                                                            @endphp
                                                            <span class="badge text-bg-{{ $resCor }}">
                                                                {{ \App\Models\Banca::resultadoLabel($banca->resultado) }}
                                                            </span>
                                                            <a href="{{ route('avaliacoes.relatorio-consolidado', $banca) }}"
                                                               target="_blank"
                                                               class="badge text-decoration-none"
                                                               style="background:#00303A; color:#fff;">
                                                                <i class="bi bi-file-text me-1"></i>Relatório
                                                            </a>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            @endif
                                        </td>
                                        <td class="text-end pe-4">
                                            @if (! $orientador)
                                                <a href="{{ route('orientacoes.edit', $trabalho) }}"
                                                   class="btn btn-sm btn-outline-warning">
                                                    Definir orientador
                                                </a>
                                            @else
                                                <div class="d-flex flex-column gap-1 align-items-end">
                                                    @if ($bancaQualif)
                                                        <a href="{{ route('bancas.edit', $bancaQualif) }}"
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-pencil-fill me-1"></i>Qualificação
                                                        </a>
                                                        @if ($bancaQualif->status === 'finalizada')
                                                            <form method="POST" action="{{ route('bancas.reabrir', $bancaQualif) }}">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-outline-warning"
                                                                        onclick="return confirm('Reabrir a banca de qualificação? Os avaliadores poderão editar suas avaliações.')">
                                                                    <i class="bi bi-unlock me-1"></i>Reabrir Qualif.
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @else
                                                        <a href="{{ route('bancas.create', ['trabalho_id' => $trabalho->id]) }}"
                                                           class="btn btn-sm btn-primary">
                                                            <i class="bi bi-plus-lg me-1"></i>Qualificação
                                                        </a>
                                                    @endif

                                                    @if ($qualificou)
                                                        @if ($bancaDefesa)
                                                            <a href="{{ route('bancas.edit', $bancaDefesa) }}"
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-pencil-fill me-1"></i>Defesa
                                                            </a>
                                                            @if ($bancaDefesa->status === 'finalizada')
                                                                <form method="POST" action="{{ route('bancas.reabrir', $bancaDefesa) }}">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-sm btn-outline-warning"
                                                                            onclick="return confirm('Reabrir a banca de defesa? Os avaliadores poderão editar suas avaliações.')">
                                                                        <i class="bi bi-unlock me-1"></i>Reabrir Defesa
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        @else
                                                            <a href="{{ route('bancas.create', ['trabalho_id' => $trabalho->id]) }}"
                                                               class="btn btn-sm btn-outline-secondary">
                                                                <i class="bi bi-plus-lg me-1"></i>Defesa
                                                            </a>
                                                        @endif
                                                    @endif
                                                </div>
                                            @endif
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
