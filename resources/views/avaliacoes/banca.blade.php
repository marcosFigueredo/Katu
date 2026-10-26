<x-app-layout>
    @php
        $discente  = $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user;
        $trabalho  = $banca->trabalhoAcademico;
        $tipoLabel = $banca->tipo === 'qualificacao' ? 'Qualificação' : 'Defesa';
        $statusColors = [
            'cadastrada'   => 'secondary',
            'em_avaliacao' => 'warning',
            'finalizada'   => 'success',
            'cancelada'    => 'danger',
        ];
        $statusLabels = [
            'cadastrada'   => 'Cadastrada',
            'em_avaliacao' => 'Em avaliação',
            'finalizada'   => 'Finalizada',
            'cancelada'    => 'Cancelada',
        ];
    @endphp

    <div class="container-fluid" style="max-width:900px;">

        {{-- Cabeçalho --}}
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="{{ route('avaliacoes.index') }}" class="text-muted text-decoration-none small">
                <i class="bi bi-arrow-left me-1"></i>Minhas bancas
            </a>
        </div>

        <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
            <h1 class="h3 fw-bold text-dark mb-0">Banca de {{ $tipoLabel }}</h1>
            <span class="badge bg-{{ $statusColors[$banca->status] ?? 'secondary' }} fs-6">
                {{ $statusLabels[$banca->status] ?? $banca->status }}
            </span>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ $errors->first() }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Info da banca --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <p class="text-muted small mb-1">Discente</p>
                        <p class="fw-semibold mb-0">{{ $discente?->name ?? '—' }}</p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted small mb-1">Programa</p>
                        <p class="fw-semibold mb-0">{{ $trabalho?->programa?->nome ?? '—' }}</p>
                    </div>
                    @if ($trabalho?->titulo)
                        <div class="col-12">
                            <p class="text-muted small mb-1">Título do trabalho</p>
                            <p class="fw-semibold mb-0">{{ $trabalho->titulo }}</p>
                        </div>
                    @endif
                    @if ($banca->data_hora)
                        <div class="col-sm-6">
                            <p class="text-muted small mb-1">Data e hora</p>
                            <p class="fw-semibold mb-0">
                                {{ $banca->data_hora->format('d/m/Y \à\s H:i') }}
                            </p>
                        </div>
                    @endif
                    @if ($banca->local)
                        <div class="col-sm-6">
                            <p class="text-muted small mb-1">Local</p>
                            <p class="fw-semibold mb-0">{{ $banca->local }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ================================================================
             ÁREA DO AVALIADOR — Meus baremas
        ================================================================ --}}
        @if ($ehMembro && $meuMembro && $baremas->isNotEmpty())
            <h2 class="h5 fw-bold text-dark mb-3">
                <i class="bi bi-clipboard2-check me-2" style="color:#00303A;"></i>
                Minha avaliação
            </h2>

            @if (in_array($banca->status, ['cancelada']))
                <div class="alert alert-danger mb-4">Esta banca foi cancelada.</div>
            @else
                <div class="row g-3 mb-5">
                    @foreach ($baremas as $barema)
                        @php
                            $av           = $minhasAvaliacoes[$barema->id] ?? null;
                            $confidencial = str_contains($barema->tipo, 'confidencial');
                            $avStatus     = $av?->status ?? 'pendente';
                            $avBadge      = match($avStatus) {
                                'enviada'  => 'success',
                                'rascunho' => 'warning',
                                default    => 'secondary',
                            };
                            $avLabel      = match($avStatus) {
                                'enviada'  => 'Enviada',
                                'rascunho' => 'Rascunho salvo',
                                default    => 'Pendente',
                            };
                            $podeEditar = ! in_array($banca->status, ['finalizada', 'cancelada']);
                        @endphp

                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body d-flex flex-column gap-2">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <i class="bi bi-{{ $confidencial ? 'lock-fill' : 'file-text' }} fs-5"
                                           style="color:#00303A;"></i>
                                        <span class="fw-semibold">
                                            {{ $confidencial ? 'Avaliação Confidencial' : 'Avaliação' }}
                                        </span>
                                        <span class="badge bg-{{ $avBadge }} ms-auto">{{ $avLabel }}</span>
                                    </div>

                                    <p class="text-muted small mb-0">{{ $barema->nome }}</p>

                                    @if ($av?->nota_final !== null)
                                        <p class="mb-0">
                                            <span class="fw-bold fs-5" style="color:#00303A;">
                                                {{ number_format($av->nota_final, 1) }}
                                            </span>
                                            <span class="text-muted small">/ 10,0</span>
                                        </p>
                                    @endif

                                    @if ($av?->enviada_em)
                                        <p class="text-muted small mb-0">
                                            Enviada em {{ $av->enviada_em->format('d/m/Y \à\s H:i') }}
                                        </p>
                                    @endif
                                </div>
                                <div class="card-footer bg-white border-top-0 pb-3 px-3 d-flex flex-column gap-1">
                                    @if ($podeEditar)
                                        <a href="{{ route('avaliacoes.form', [$banca, $barema]) }}"
                                           class="btn btn-sm w-100"
                                           style="background:#00303A; color:#fff;">
                                            <i class="bi bi-{{ $avStatus === 'pendente' ? 'pencil-square' : 'pencil' }} me-1"></i>
                                            {{ $avStatus === 'pendente' ? 'Iniciar avaliação' : ($avStatus === 'enviada' ? 'Editar avaliação' : 'Continuar rascunho') }}
                                        </a>
                                    @else
                                        <a href="{{ route('avaliacoes.form', [$banca, $barema]) }}"
                                           class="btn btn-sm btn-outline-secondary w-100">
                                            <i class="bi bi-eye me-1"></i>Ver avaliação
                                        </a>
                                    @endif
                                    @if ($av && $av->status === 'enviada')
                                        <a href="{{ route('avaliacoes.relatorio-individual', [$banca, $barema]) }}"
                                           target="_blank"
                                           class="btn btn-sm btn-outline-secondary w-100">
                                            <i class="bi bi-printer me-1"></i>Imprimir
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($baremas->isEmpty())
                    <div class="alert alert-warning mb-4">
                        Nenhum barema ativo encontrado para este programa e tipo de banca.
                        Solicite à secretaria que cadastre um barema.
                    </div>
                @endif
            @endif
        @endif

        {{-- ================================================================
             ÁREA DO ORIENTADOR — Status de todos os avaliadores
        ================================================================ --}}
        @if ($ehOrientador)
            <h2 class="h5 fw-bold text-dark mb-3">
                <i class="bi bi-people me-2" style="color:#00303A;"></i>
                Status dos avaliadores
            </h2>

            @if ($avaliadores->isEmpty())
                <div class="alert alert-warning mb-4">
                    Nenhum avaliador cadastrado nesta banca.
                </div>
            @else
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Avaliador</th>
                                    @foreach ($baremas as $barema)
                                        <th class="text-center">
                                            {{ str_contains($barema->tipo, 'confidencial') ? 'Confidencial' : 'Avaliação' }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($avaliadores as $membro)
                                    @php
                                        $nomeDocente = $membro->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '—';
                                        $avaliacoesMembro = $membro->avaliacoes->keyBy('barema_id');
                                    @endphp
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $nomeDocente }}</td>
                                        @foreach ($baremas as $barema)
                                            @php
                                                $av = $avaliacoesMembro[$barema->id] ?? null;
                                                $st = $av?->status ?? 'pendente';
                                                $badge = match($st) {
                                                    'enviada'  => 'success',
                                                    'rascunho' => 'warning',
                                                    default    => 'secondary',
                                                };
                                                $label = match($st) {
                                                    'enviada'  => 'Enviada',
                                                    'rascunho' => 'Rascunho',
                                                    default    => 'Pendente',
                                                };
                                            @endphp
                                            <td class="text-center">
                                                <span class="badge bg-{{ $badge }}">{{ $label }}</span>
                                                @if ($av?->nota_final !== null)
                                                    <br>
                                                    <small class="text-muted">{{ number_format($av->nota_final, 1) }}</small>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Progresso --}}
                @if ($totalEsperado > 0)
                    <div class="mb-4">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Progresso geral</span>
                            <span>{{ $totalEnviadas }} de {{ $totalEsperado }} avaliações enviadas</span>
                        </div>
                        <div class="progress" style="height:8px;">
                            <div class="progress-bar"
                                 style="background:#00303A; width:{{ $totalEsperado > 0 ? round(($totalEnviadas / $totalEsperado) * 100) : 0 }}%;">
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Finalizar banca --}}
                @if ($banca->status !== 'finalizada' && $banca->status !== 'cancelada')
                    @if ($podeFinalizar)
                        <div class="card border-success border-0 shadow-sm mt-3" style="border-left:4px solid #16a34a;">
                            <div class="card-body">
                                <h6 class="fw-bold text-success mb-3">
                                    <i class="bi bi-check-circle me-1"></i>
                                    Todas as avaliações recebidas — registre o resultado
                                </h6>
                                <form method="POST" action="{{ route('avaliacoes.finalizar', $banca) }}">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Resultado da banca</label>
                                        <select name="resultado" class="form-select" required>
                                            <option value="" disabled selected>Selecione o resultado…</option>
                                            <option value="aprovado">Aprovado</option>
                                            <option value="aprovado_modificacoes">Aprovado com modificações</option>
                                            <option value="reprovado">Reprovado</option>
                                        </select>
                                        @error('resultado')
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <button type="submit" class="btn btn-success"
                                            onclick="return confirm('Confirma a finalização desta banca? Esta ação enviará o resultado ao discente e encerrará a possibilidade de edição.')">
                                        <i class="bi bi-check-circle me-1"></i>Finalizar e notificar discente
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <p class="text-muted small mt-2">
                            <i class="bi bi-hourglass-split me-1"></i>
                            Aguardando todas as avaliações serem enviadas para liberar a finalização.
                        </p>
                    @endif
                @endif

                @if ($banca->status === 'finalizada')
                    <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-check-circle-fill fs-5"></i>
                        <div>
                            <strong>Banca finalizada.</strong>
                            @if ($banca->resultado)
                                Resultado:
                                <strong>{{ \App\Models\Banca::resultadoLabel($banca->resultado) }}</strong>
                            @endif
                            @if ($banca->nota_media !== null)
                                — Nota média: <strong>{{ number_format($banca->nota_media, 1, ',', '') }}</strong>
                            @endif
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap mt-2">
                        <a href="{{ route('avaliacoes.ata', $banca) }}"
                           target="_blank"
                           class="btn btn-sm"
                           style="background:#00303A; color:#fff;">
                            <i class="bi bi-file-earmark-text me-1"></i>Ata da banca
                        </a>
                        <a href="{{ route('avaliacoes.folha-aprovacao', $banca) }}"
                           class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Folha de aprovação
                        </a>
                        <a href="{{ route('avaliacoes.relatorio-consolidado', $banca) }}"
                           target="_blank"
                           class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-file-text me-1"></i>Relatório consolidado
                        </a>
                    </div>
                @endif
            @endif
        @endif

    </div>
</x-app-layout>
