<x-app-layout>
    <div class="container-fluid">

        {{-- Breadcrumb --}}
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('orientacoes.index') }}" class="text-decoration-none">Orientações</a>
                </li>
                <li class="breadcrumb-item active">Gerenciar vínculo</li>
            </ol>
        </nav>

        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1" style="color: #00303A;">Gerenciar Orientação</h1>
            <p class="text-muted mb-0">
                Atribua ou altere o orientador e coorientadores do vínculo acadêmico do discente.
            </p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row g-4">

            {{-- Dados do discente --}}
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h6 class="fw-bold mb-0">
                            <i class="bi bi-mortarboard-fill me-2" style="color:#00303A;"></i>Discente
                        </h6>
                    </div>
                    <div class="card-body">
                        @php
                            $discente = $trabalho->discenteVinculo?->discenteProfile?->user;
                            $vinculo  = $trabalho->discenteVinculo;
                        @endphp

                        <dl class="row mb-0 small">
                            <dt class="col-5 text-muted fw-normal">Nome</dt>
                            <dd class="col-7 fw-semibold mb-2">{{ $discente?->name ?? '—' }}</dd>

                            <dt class="col-5 text-muted fw-normal">Matrícula</dt>
                            <dd class="col-7 mb-2">{{ $vinculo?->matricula ?? '—' }}</dd>

                            <dt class="col-5 text-muted fw-normal">Nível</dt>
                            <dd class="col-7 mb-2">
                                {{ match($vinculo?->nivel) {
                                    'mestrado'  => 'Mestrado',
                                    'doutorado' => 'Doutorado',
                                    default     => $vinculo?->nivel ?? '—',
                                } }}
                            </dd>

                            <dt class="col-5 text-muted fw-normal">Programa</dt>
                            <dd class="col-7 mb-2">{{ $trabalho->programa?->nome ?? '—' }}</dd>

                            <dt class="col-5 text-muted fw-normal">Linha</dt>
                            <dd class="col-7 mb-0">{{ $vinculo?->linhaPesquisa?->nome ?? '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            {{-- Formulário de orientação --}}
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h6 class="fw-bold mb-0">
                            <i class="bi bi-arrow-left-right me-2" style="color:#00303A;"></i>Vínculo de Orientação
                        </h6>
                    </div>
                    <div class="card-body">

                        <form method="POST" action="{{ route('orientacoes.update', $trabalho) }}">
                            @csrf
                            @method('PUT')

                            {{-- Orientador principal --}}
                            <div class="mb-4">
                                <label for="orientador_vinculo_id" class="form-label fw-semibold">
                                    Orientador principal
                                </label>

                                @if (empty($opcoesOrientador))
                                    <div class="alert alert-warning small py-2 mb-0">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                        Nenhum docente com permissão de orientação cadastrado neste programa.
                                    </div>
                                @else
                                    <x-select-busca name="orientador_vinculo_id" id="orientador_vinculo_id"
                                        :opcoes="$opcoesOrientador"
                                        :selecionado="old('orientador_vinculo_id', $orientadorAtual?->docente_programa_vinculo_id)"
                                        :invalido="$errors->has('orientador_vinculo_id')"
                                        placeholder="Digite o nome do orientador…" />
                                    @error('orientador_vinculo_id')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">
                                        Apenas docentes do programa com permissão de orientação.
                                    </div>
                                @endif
                            </div>

                            {{-- Coorientadores --}}
                            @if ($maxCoorientadores > 0)
                                <div class="mb-4">
                                    <label class="form-label fw-semibold mb-0">
                                        Coorientadores
                                        <span class="text-muted fw-normal">(opcional — até {{ $maxCoorientadores }})</span>
                                    </label>
                                    <div class="form-text mt-0 mb-2">
                                        Podem ser docentes internos ou externos vinculados ao programa.
                                    </div>

                                    @if (count($coorientadoresAtuais) > $maxCoorientadores)
                                        <div class="alert alert-warning small py-2">
                                            Este discente tem {{ count($coorientadoresAtuais) }} coorientadores, acima do limite atual do programa
                                            ({{ $maxCoorientadores }}). Ajuste antes de salvar.
                                        </div>
                                    @endif

                                    <div class="row g-2">
                                        @for ($i = 0; $i < $maxCoorientadores; $i++)
                                            <div class="col-md-6">
                                                <label for="coorientador_{{ $i }}" class="form-label small text-muted mb-1">
                                                    Coorientador {{ $i + 1 }}
                                                </label>
                                                <x-select-busca name="coorientadores[{{ $i }}]" id="coorientador_{{ $i }}"
                                                    :opcoes="$opcoesCoorientador"
                                                    :selecionado="old('coorientadores.' . $i, $coorientadoresAtuais[$i] ?? null)"
                                                    :invalido="$errors->has('coorientadores')"
                                                    placeholder="Digite o nome do coorientador…" />
                                            </div>
                                        @endfor
                                    </div>
                                    @error('coorientadores')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif

                            <div class="d-flex gap-2 pt-2 border-top">
                                <button type="submit" class="btn btn-primary fw-semibold">
                                    <i class="bi bi-floppy-fill me-2"></i>Salvar orientação
                                </button>
                                <a href="{{ route('orientacoes.index') }}" class="btn btn-outline-secondary">
                                    Cancelar
                                </a>
                            </div>

                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
