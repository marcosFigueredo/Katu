<x-app-layout>
    <div class="container-fluid">

        <div class="mb-4">
            <h1 class="h3 mb-1 fw-bold text-dark">Área do Docente</h1>
            <p class="text-muted mb-0">Gerencie suas participações em bancas examinadoras.</p>
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

        @if (! $vinculo)
            <div class="alert alert-warning">
                Seu perfil de docente ainda não está vinculado a nenhum programa. Procure a secretaria.
            </div>
        @else

        @if ($vinculo->ehPermanente())
            @php $projetoAtivo = $vinculo->projetoAtivo(); @endphp
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <div class="small text-muted"><i class="bi bi-journal-text me-1"></i>Meu projeto de pesquisa</div>
                        @if ($projetoAtivo)
                            <div class="fw-semibold">{{ $projetoAtivo->titulo }}</div>
                        @else
                            <div class="text-danger fw-semibold">Cadastre seu projeto de pesquisa: seus orientandos precisam dele para enviar a qualificação e a defesa.</div>
                        @endif
                    </div>
                    <a href="{{ route('meu-projeto.edit') }}" class="btn btn-sm btn-outline-primary">{{ $projetoAtivo ? 'Ver ou trocar' : 'Cadastrar' }}</a>
                </div>
            </div>
        @endif

        {{-- ================================================================
             BANCAS ONDE É AVALIADOR
        ================================================================ --}}
        <div class="mb-5">
            <h2 class="h5 fw-bold text-dark mb-3">
                <i class="bi bi-clipboard2-check me-2" style="color:#00303A;"></i>
                Bancas para avaliar
            </h2>

            @if ($bancasAvaliador->isEmpty())
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center text-muted py-4">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        Nenhuma banca atribuída para avaliação.
                    </div>
                </div>
            @else
                <div class="row g-3">
                    @foreach ($bancasAvaliador as $banca)
                        @php
                            $discente   = $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user;
                            $trabalho   = $banca->trabalhoAcademico;
                            $meuMembro  = $banca->meuMembro;
                            $minhasAv   = $meuMembro?->avaliacoes ?? collect();

                            $statusColors = [
                                'cadastrada'    => 'secondary',
                                'em_avaliacao'  => 'warning',
                                'finalizada'    => 'success',
                                'cancelada'     => 'danger',
                            ];
                            $statusLabels = [
                                'cadastrada'    => 'Cadastrada',
                                'em_avaliacao'  => 'Em avaliação',
                                'finalizada'    => 'Finalizada',
                                'cancelada'     => 'Cancelada',
                            ];
                            $tipoLabel = $banca->tipo === 'qualificacao' ? 'Qualificação' : 'Defesa';
                        @endphp

                        <div class="col-md-6 col-xl-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-2">
                                    <span class="badge" style="background:#00303A;">{{ $tipoLabel }}</span>
                                    <span class="badge bg-{{ $statusColors[$banca->status] ?? 'secondary' }}">
                                        {{ $statusLabels[$banca->status] ?? $banca->status }}
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p class="mb-1 fw-semibold text-dark">{{ $discente?->name ?? '—' }}</p>

                                    @if ($trabalho?->titulo)
                                        <p class="text-muted small mb-2" style="line-height:1.3;">
                                            {{ Str::limit($trabalho->titulo, 80) }}
                                        </p>
                                    @endif

                                    @if ($banca->data_hora)
                                        <p class="small mb-1 text-muted">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            {{ $banca->data_hora->format('d/m/Y \à\s H:i') }}
                                        </p>
                                    @endif
                                    @if ($banca->local)
                                        <p class="small mb-2 text-muted">
                                            <i class="bi bi-geo-alt me-1"></i>
                                            {{ $banca->local }}
                                        </p>
                                    @endif

                                    {{-- Status de cada avaliação --}}
                                    <div class="mt-2 d-flex flex-column gap-1">
                                        @foreach ($minhasAv as $av)
                                            @php
                                                $avBadge = $av->status === 'enviada' ? 'success' : 'warning';
                                                $avLabel = $av->status === 'enviada' ? 'Enviada' : 'Rascunho';
                                                $confidencial = str_contains($av->barema?->tipo ?? '', 'confidencial');
                                            @endphp
                                            <span class="badge bg-{{ $avBadge }} bg-opacity-10 text-{{ $avBadge }} border border-{{ $avBadge }} fw-normal small">
                                                <i class="bi bi-{{ $confidencial ? 'lock' : 'file-text' }} me-1"></i>
                                                {{ $confidencial ? 'Confidencial' : 'Avaliação' }}: {{ $avLabel }}
                                                @if ($av->nota_final !== null)
                                                    — {{ number_format($av->nota_final, 1) }}
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="card-footer bg-white border-top-0 pt-0 pb-3 px-3">
                                    <a href="{{ route('avaliacoes.banca', $banca) }}"
                                       class="btn btn-sm w-100"
                                       style="background:#00303A; color:#fff;">
                                        <i class="bi bi-arrow-right-circle me-1"></i>
                                        Ver detalhes
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ================================================================
             BANCAS ONDE É ORIENTADOR (só para quem orienta)
        ================================================================ --}}
        @if ($orientaAlgum || $bancasOrientador->isNotEmpty())
        <div class="mb-4">
            <h2 class="h5 fw-bold text-dark mb-3">
                <i class="bi bi-mortarboard me-2" style="color:#00303A;"></i>
                Bancas que você orienta
            </h2>

            @if ($bancasOrientador->isEmpty())
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center text-muted py-4">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        Nenhuma banca de orientandos encontrada.
                    </div>
                </div>
            @else
                <div class="row g-3">
                    @foreach ($bancasOrientador as $banca)
                        @php
                            $discente  = $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user;
                            $trabalho  = $banca->trabalhoAcademico;
                            $tipoLabel = $banca->tipo === 'qualificacao' ? 'Qualificação' : 'Defesa';
                            $statusColors = [
                                'cadastrada'    => 'secondary',
                                'em_avaliacao'  => 'warning',
                                'finalizada'    => 'success',
                                'cancelada'     => 'danger',
                            ];
                            $statusLabels = [
                                'cadastrada'    => 'Cadastrada',
                                'em_avaliacao'  => 'Em avaliação',
                                'finalizada'    => 'Finalizada',
                                'cancelada'     => 'Cancelada',
                            ];
                        @endphp

                        <div class="col-md-6 col-xl-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-2">
                                    <span class="badge" style="background:#00303A;">{{ $tipoLabel }}</span>
                                    <span class="badge bg-{{ $statusColors[$banca->status] ?? 'secondary' }}">
                                        {{ $statusLabels[$banca->status] ?? $banca->status }}
                                    </span>
                                    @if ($banca->podeFinalizar)
                                        <span class="badge bg-success ms-auto">Pronto para finalizar</span>
                                    @endif
                                </div>
                                <div class="card-body">
                                    <p class="mb-1 fw-semibold text-dark">{{ $discente?->name ?? '—' }}</p>

                                    @if ($trabalho?->titulo)
                                        <p class="text-muted small mb-2" style="line-height:1.3;">
                                            {{ Str::limit($trabalho->titulo, 80) }}
                                        </p>
                                    @endif

                                    @if ($banca->data_hora)
                                        <p class="small mb-1 text-muted">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            {{ $banca->data_hora->format('d/m/Y \à\s H:i') }}
                                        </p>
                                    @endif

                                    {{-- Progresso das avaliações --}}
                                    @if ($banca->totalEsperado > 0)
                                        <div class="mt-2">
                                            <div class="d-flex justify-content-between small text-muted mb-1">
                                                <span>Avaliações enviadas</span>
                                                <span>{{ $banca->totalEnviadas }} / {{ $banca->totalEsperado }}</span>
                                            </div>
                                            <div class="progress" style="height:6px;">
                                                <div class="progress-bar"
                                                     style="background:#00303A; width:{{ $banca->totalEsperado > 0 ? round(($banca->totalEnviadas / $banca->totalEsperado) * 100) : 0 }}%;">
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div class="card-footer bg-white border-top-0 pt-0 pb-3 px-3 d-flex gap-2">
                                    <a href="{{ route('avaliacoes.banca', $banca) }}"
                                       class="btn btn-sm btn-outline-secondary flex-fill">
                                        <i class="bi bi-eye me-1"></i>Ver
                                    </a>
                                    @if ($banca->podeFinalizar)
                                        <form method="POST" action="{{ route('avaliacoes.finalizar', $banca) }}" class="flex-fill">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success w-100"
                                                    onclick="return confirm('Confirma a finalização desta banca?')">
                                                <i class="bi bi-check-circle me-1"></i>Finalizar
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @endif

        {{-- ================================================================
             BANCAS FINALIZADAS DE QUE PARTICIPOU — dados para o Lattes
        ================================================================ --}}
        <div class="mb-4">
            <h2 class="h5 fw-bold text-dark mb-3">
                <i class="bi bi-person-vcard me-2" style="color:#00303A;"></i>
                Bancas do {{ $vinculo->programa?->sigla ?? 'programa' }} de que participou
            </h2>

            @if ($bancasParticipadas->isEmpty())
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center text-muted py-4">
                        Nenhuma banca finalizada ainda. Os dados para o Lattes aparecem aqui quando a banca é finalizada.
                    </div>
                </div>
            @else
                <div class="card border-0 shadow-sm">
                    <ul class="list-group list-group-flush">
                        @foreach ($bancasParticipadas as $banca)
                            <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user?->name ?? '—' }}</div>
                                    <small class="text-muted">
                                        {{ $banca->tipo === 'defesa' ? 'Defesa' : 'Qualificação' }}
                                        · {{ $banca->data_hora?->format('d/m/Y') }}
                                        · {{ \Illuminate\Support\Str::limit($banca->trabalhoAcademico?->titulo ?? '', 90) }}
                                    </small>
                                </div>
                                <a href="{{ route('avaliacoes.lattes', $banca) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-clipboard-data me-1"></i>Dados para o Lattes
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @endif {{-- fim @if $vinculo --}}

    </div>
</x-app-layout>
