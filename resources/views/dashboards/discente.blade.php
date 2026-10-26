<x-app-layout>
    @php
        $usuario = auth()->user()->load([
            'discenteProfile.vinculosPrograma.programa',
            'discenteProfile.vinculosPrograma.linhaPesquisa',
            'discenteProfile.vinculosPrograma.trabalhoAcademico.bancas',
            'discenteProfile.vinculosPrograma.trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user',
        ]);

        $discenteProfile = $usuario->discenteProfile;
        $vinculo  = $discenteProfile?->vinculosPrograma?->first();
        $trabalho = $vinculo?->trabalhoAcademico;
        $bancas   = $trabalho?->bancas ?? collect();

        $orientador     = $trabalho?->orientacoes?->firstWhere('tipo', 'orientador');
        $coorientadores = $trabalho?->orientacoes?->where('tipo', 'coorientador') ?? collect();
    @endphp

    @if (blank($usuario->discenteProfile?->orcid_url))
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>Informe seu ORCID no <a href="{{ route('profile.edit') }}">seu cadastro</a>. Ele é obrigatório para qualificação e defesa.</div>
        </div>
    @endif

    <div class="container-fluid">

        <div class="mb-4">
            <h1 class="h3 mb-1 fw-bold text-dark">
                Área do Discente
            </h1>

            <p class="text-muted mb-0">
                Acompanhe suas informações acadêmicas e, futuramente, suas bancas de qualificação e defesa.
            </p>
        </div>

        <div class="row g-4">

            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h5 fw-bold text-dark mb-3">
                            Dados do Discente
                        </h2>

                        <p class="mb-2">
                            <strong>Nome:</strong>
                            {{ $usuario->name }}
                        </p>

                        <p class="mb-2">
                            <strong>E-mail:</strong>
                            {{ $usuario->email }}
                        </p>

                        <p class="mb-2">
                            <strong>Matrícula:</strong>
                            {{ $vinculo->matricula ?? 'Não informada' }}
                        </p>

                        <p class="mb-2">
                            <strong>Nível:</strong>
                            {{ $vinculo ? ucfirst($vinculo->nivel) : 'Não informado' }}
                        </p>

                        <p class="mb-0">
                            <strong>Status:</strong>
                            {{ $vinculo ? ucfirst($vinculo->status) : 'Não informado' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h5 fw-bold text-dark mb-3">
                            Vínculo Acadêmico
                        </h2>

                        <p class="mb-2">
                            <strong>Programa:</strong>
                            {{ $vinculo?->programa?->sigla ?? $vinculo?->programa?->nome ?? 'Não informado' }}
                        </p>

                        <p class="mb-2">
                            <strong>Linha de pesquisa:</strong>
                            {{ $vinculo?->linhaPesquisa?->nome ?? 'Não informada' }}
                        </p>

                        <p class="mb-2">
                            <strong>Data de ingresso:</strong>
                            {{ $vinculo?->data_ingresso ? \Carbon\Carbon::parse($vinculo->data_ingresso)->format('d/m/Y') : 'Não informada' }}
                        </p>

                        <p class="mb-0">
                            <strong>Situação do cadastro:</strong>

                            @if ($usuario->cadastro_status === 'completo')
                                <span class="badge rounded-pill text-bg-success">
                                    Completo
                                </span>
                            @else
                                <span class="badge rounded-pill text-bg-warning">
                                    Pendente
                                </span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h5 fw-bold text-dark mb-3">
                            <i class="bi bi-mortarboard-fill me-2"></i>Orientação
                        </h2>

                        @if ($orientador)
                            @php $orientadorUser = $orientador->docenteProgramaVinculo?->docenteProfile?->user; @endphp
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 h-100">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">Orientador</div>
                                        <div class="fw-semibold">{{ $orientadorUser?->name ?? '—' }}</div>
                                        <div class="small text-muted">
                                            <i class="bi bi-envelope-fill me-1"></i>{{ $orientadorUser?->email ?? 'E-mail não informado' }}
                                        </div>
                                    </div>
                                </div>

                                @if ($coorientadores->isNotEmpty())
                                    @foreach ($coorientadores as $co)
                                        @php $coUser = $co->docenteProgramaVinculo?->docenteProfile?->user; @endphp
                                        <div class="col-md-6">
                                            <div class="p-3 bg-light rounded-3 h-100">
                                                <div class="text-muted small text-uppercase fw-semibold mb-1">Coorientador(a)</div>
                                                <div class="fw-semibold">{{ $coUser?->name ?? '—' }}</div>
                                                <div class="small text-muted">
                                                    <i class="bi bi-envelope-fill me-1"></i>{{ $coUser?->email ?? 'E-mail não informado' }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        @else
                            <p class="text-muted mb-0">
                                <i class="bi bi-exclamation-circle me-1"></i>
                                Orientador ainda não definido. Procure a secretaria do programa.
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h5 fw-bold text-dark mb-3">
                            <i class="bi bi-person-workspace me-2"></i>Bancas
                        </h2>

                        @if ($bancas->isEmpty())
                            <p class="text-muted mb-0">
                                Nenhuma banca cadastrada ainda. As informações aparecerão aqui após o registro pela secretaria.
                            </p>
                        @else
                            <div class="row g-3">
                                @foreach ($bancas as $banca)
                                    @php
                                        $tipoCor   = match($banca->tipo) {
                                            'qualificacao'              => 'info',
                                            'defesa'                    => 'primary',
                                            'qualificacao_confidencial' => 'warning',
                                            'defesa_confidencial'       => 'danger',
                                            default                     => 'secondary',
                                        };
                                        $tipoLabel = match($banca->tipo) {
                                            'qualificacao'              => 'Qualificação',
                                            'defesa'                    => 'Defesa',
                                            'qualificacao_confidencial' => 'Qualif. Confidencial',
                                            'defesa_confidencial'       => 'Defesa Confidencial',
                                            default                     => $banca->tipo,
                                        };
                                        $statusCor = match($banca->status) {
                                            'cadastrada'   => 'secondary',
                                            'em_avaliacao' => 'warning',
                                            'finalizada'   => 'success',
                                            'cancelada'    => 'danger',
                                            default        => 'secondary',
                                        };
                                        $statusLabel = match($banca->status) {
                                            'cadastrada'   => 'Agendada',
                                            'em_avaliacao' => 'Em avaliação',
                                            'finalizada'   => 'Finalizada',
                                            'cancelada'    => 'Cancelada',
                                            default        => $banca->status,
                                        };
                                        $orientador     = $trabalho?->orientacoes?->firstWhere('tipo', 'orientador');
                                        $coorientadores = $trabalho?->orientacoes?->where('tipo', 'coorientador') ?? collect();
                                    @endphp
                                    <div class="col-md-6">
                                        <div class="card border shadow-sm h-100">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-1">
                                                    <span class="badge text-bg-{{ $tipoCor }} fs-6 px-3 py-2">
                                                        {{ $tipoLabel }}
                                                    </span>
                                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                                        <span class="badge text-bg-{{ $statusCor }}">{{ $statusLabel }}</span>
                                                        @if ($banca->status === 'finalizada' && $banca->resultado)
                                                            @php
                                                                $resCor = match($banca->resultado) {
                                                                    'aprovado'              => 'success',
                                                                    'aprovado_modificacoes' => 'warning',
                                                                    'reprovado'             => 'danger',
                                                                    default                 => 'secondary',
                                                                };
                                                            @endphp
                                                            <span class="badge text-bg-{{ $resCor }} fw-bold">
                                                                {{ \App\Models\Banca::resultadoLabel($banca->resultado) }}
                                                            </span>
                                                            @if ($banca->nota_media !== null)
                                                                <span class="badge bg-light text-dark border fw-semibold">
                                                                    Nota {{ number_format($banca->nota_media, 1, ',', '') }}
                                                                </span>
                                                            @endif
                                                        @endif
                                                    </div>
                                                </div>

                                                <dl class="row small mb-0">
                                                    <dt class="col-5 text-muted fw-normal">Data e hora</dt>
                                                    <dd class="col-7 fw-semibold mb-2">
                                                        @if ($banca->data_hora)
                                                            {{ $banca->data_hora->format('d/m/Y') }} às {{ $banca->data_hora->format('H:i') }}
                                                        @else
                                                            <span class="text-muted">A definir</span>
                                                        @endif
                                                    </dd>

                                                    @if ($banca->local)
                                                        <dt class="col-5 text-muted fw-normal">Local</dt>
                                                        <dd class="col-7 mb-2">{{ $banca->local }}</dd>
                                                    @endif

                                                    @if ($orientador)
                                                        <dt class="col-5 text-muted fw-normal">Orientador</dt>
                                                        <dd class="col-7 mb-2">
                                                            {{ $orientador->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '—' }}
                                                        </dd>
                                                    @endif

                                                    @if ($coorientadores->isNotEmpty())
                                                        <dt class="col-5 text-muted fw-normal">Coorientador(es)</dt>
                                                        <dd class="col-7 mb-0">
                                                            @foreach ($coorientadores as $co)
                                                                <div>{{ $co->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '—' }}</div>
                                                            @endforeach
                                                        </dd>
                                                    @endif
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @php
                                $podeEnviarTrabalho = $bancas->contains(
                                    fn ($b) => in_array($b->homologacao_status, ['proposta', 'devolvida'], true) && $b->status !== 'cancelada'
                                );
                            @endphp
                            @if ($podeEnviarTrabalho)
                                <div class="mt-3">
                                    <a href="{{ route('meu-trabalho.index') }}" class="btn btn-primary btn-sm">
                                        <i class="bi bi-cloud-upload-fill me-1"></i>Enviar trabalho em PDF
                                    </a>
                                </div>
                            @endif
                        @endif

                    </div>
                </div>
            </div>

        </div>

    </div>
</x-app-layout>