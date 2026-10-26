<x-app-layout>
    <div class="container-fluid">

        @if (session('status'))
        <div class="alert alert-success shadow-sm">
            {{ session('status') }}
        </div>
        @endif

        @php
            $titulosClasse = [
                'todos' => ['titulo' => 'Todos os Usuários', 'subtitulo' => 'Cadastro e gerenciamento geral de usuários do programa.'],
                'docentes' => ['titulo' => 'Docentes do Programa', 'subtitulo' => 'Corpo docente permanente e colaborador vinculado ao programa.'],
                'discentes' => ['titulo' => 'Discentes', 'subtitulo' => 'Estudantes matriculados nos cursos de pós-graduação do programa.'],
                'externos' => ['titulo' => 'Docentes Externos', 'subtitulo' => 'Avaliadores e colaboradores externos cadastrados para bancas.'],
                'coordenador' => ['titulo' => 'Coordenação do Programa', 'subtitulo' => 'Docentes designados para a coordenação e vice-coordenação.'],
            ];
            $infoAtual = $titulosClasse[$classe] ?? $titulosClasse['todos'];
        @endphp

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h1 class="h3 mb-1 fw-bold text-dark">
                    {{ $infoAtual['titulo'] }}
                </h1>

                <p class="text-muted mb-0">
                    {{ $infoAtual['subtitulo'] }}
                </p>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('usuarios.importar') }}" class="btn btn-outline-secondary fw-semibold">
                    <i class="bi bi-file-earmark-arrow-up me-1"></i>Importar discentes
                </a>
                <a href="{{ route('usuarios.create') }}"
                    class="btn text-white fw-semibold"
                    style="background-color: #00303A;">
                    <i class="bi bi-person-plus-fill me-1"></i>Novo usuário
                </a>
            </div>
        </div>

        {{-- Filtros por Classe e Busca Textual --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <ul class="nav nav-pills gap-1 bg-white p-1 rounded-3 shadow-sm border">
                <li class="nav-item">
                    <a class="nav-link py-2 px-3 fw-semibold {{ $classe === 'todos' ? 'active text-white' : 'text-secondary' }}"
                       style="{{ $classe === 'todos' ? 'background-color: #00303A;' : '' }}"
                       href="{{ route('usuarios.index', array_filter(['busca' => $busca])) }}">
                        <i class="bi bi-people-fill me-1"></i> Todos
                        <span class="badge {{ $classe === 'todos' ? 'bg-white text-dark' : 'text-bg-light border' }} ms-1">
                            {{ $contagens['todos'] }}
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-2 px-3 fw-semibold {{ $classe === 'docentes' ? 'active text-white' : 'text-secondary' }}"
                       style="{{ $classe === 'docentes' ? 'background-color: #00303A;' : '' }}"
                       href="{{ route('usuarios.index', array_filter(['classe' => 'docentes', 'busca' => $busca])) }}">
                        <i class="bi bi-person-badge-fill me-1"></i> Docentes
                        <span class="badge {{ $classe === 'docentes' ? 'bg-white text-dark' : 'text-bg-light border' }} ms-1">
                            {{ $contagens['docentes'] }}
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-2 px-3 fw-semibold {{ $classe === 'discentes' ? 'active text-white' : 'text-secondary' }}"
                       style="{{ $classe === 'discentes' ? 'background-color: #00303A;' : '' }}"
                       href="{{ route('usuarios.index', array_filter(['classe' => 'discentes', 'busca' => $busca])) }}">
                        <i class="bi bi-mortarboard-fill me-1"></i> Discentes
                        <span class="badge {{ $classe === 'discentes' ? 'bg-white text-dark' : 'text-bg-light border' }} ms-1">
                            {{ $contagens['discentes'] }}
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-2 px-3 fw-semibold {{ $classe === 'externos' ? 'active text-white' : 'text-secondary' }}"
                       style="{{ $classe === 'externos' ? 'background-color: #00303A;' : '' }}"
                       href="{{ route('usuarios.index', array_filter(['classe' => 'externos', 'busca' => $busca])) }}">
                        <i class="bi bi-globe2 me-1"></i> Docentes externos
                        <span class="badge {{ $classe === 'externos' ? 'bg-white text-dark' : 'text-bg-light border' }} ms-1">
                            {{ $contagens['externos'] }}
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-2 px-3 fw-semibold {{ $classe === 'coordenador' ? 'active text-white' : 'text-secondary' }}"
                       style="{{ $classe === 'coordenador' ? 'background-color: #00303A;' : '' }}"
                       href="{{ route('usuarios.index', array_filter(['classe' => 'coordenador', 'busca' => $busca])) }}">
                        <i class="bi bi-award-fill me-1"></i> Coordenador
                        <span class="badge {{ $classe === 'coordenador' ? 'bg-white text-dark' : 'text-bg-light border' }} ms-1">
                            {{ $contagens['coordenador'] }}
                        </span>
                    </a>
                </li>
            </ul>

            {{-- Formulário de busca textual --}}
            <form method="GET" action="{{ route('usuarios.index') }}" class="d-flex gap-2">
                @if ($classe !== 'todos')
                    <input type="hidden" name="classe" value="{{ $classe }}">
                @endif
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text"
                           name="busca"
                           value="{{ $busca }}"
                           class="form-control border-start-0"
                           placeholder="Buscar por nome ou e-mail..."
                           style="min-width: 240px;">
                    @if ($busca)
                        <a href="{{ route('usuarios.index', $classe !== 'todos' ? ['classe' => $classe] : []) }}"
                           class="btn btn-outline-secondary border-start-0 bg-white"
                           title="Limpar busca">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                    <button type="submit" class="btn text-white fw-semibold" style="background-color: #00303A;">
                        Buscar
                    </button>
                </div>
            </form>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-dark">
                        {{ $infoAtual['titulo'] }}
                        @if ($busca)
                            <span class="text-muted small fw-normal ms-2">
                                (filtrado por "{{ $busca }}")
                            </span>
                        @endif
                    </span>

                    <span class="badge text-bg-light border">
                        Exibindo {{ $usuarios->count() }} registro(s)
                    </span>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Nome</th>
                                <th>E-mail</th>
                                <th>Classe / Papel</th>
                                <th>Detalhes do Vínculo</th>
                                <th>Situação</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($usuarios as $usuario)
                            @php
                                $ehCoordenador = $programa && $programa->coordenador_id === $usuario->id;
                                $ehViceCoordenador = $programa && $programa->vice_coordenador_id === $usuario->id;
                                $vinculoDocente = $usuario->docenteProfile?->vinculosPrograma?->firstWhere('programa_id', $programa?->id);
                                $vinculoDiscente = $usuario->discenteProfile?->vinculosPrograma?->firstWhere('programa_id', $programa?->id);
                            @endphp
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="fw-bold text-dark">
                                            {{ $usuario->name }}
                                        </div>

                                        @if ($ehCoordenador)
                                            <span class="badge text-bg-primary" title="Coordenador do Programa">
                                                <i class="bi bi-star-fill me-1"></i>Coordenador(a)
                                            </span>
                                        @elseif ($ehViceCoordenador)
                                            <span class="badge text-bg-info text-white" title="Vice-Coordenador do Programa">
                                                <i class="bi bi-star-half me-1"></i>Vice-Coordenador(a)
                                            </span>
                                        @endif
                                    </div>

                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        @if ($usuario->cadastro_status === 'pendente')
                                            <span class="badge rounded-pill text-bg-warning" style="font-size: 11px;">
                                                Cadastro pendente
                                            </span>
                                        @else
                                            <span class="badge rounded-pill text-bg-success" style="font-size: 11px;">
                                                Cadastro completo
                                            </span>
                                        @endif

                                        @if ($usuario->primeiro_acesso)
                                            <span class="badge rounded-pill text-bg-secondary" style="font-size: 11px;">
                                                Primeiro acesso pendente
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <td class="text-muted">
                                    {{ $usuario->email }}
                                    @if ($usuario->docenteProfile?->orcid_url || $usuario->discenteProfile?->orcid_url)
                                        <div class="small text-muted mt-1">
                                            <i class="bi bi-person-badge text-success me-1"></i>
                                            <span style="font-family: monospace; font-size: 11px;">
                                                {{ str_replace('https://orcid.org/', '', $usuario->docenteProfile?->orcid_url ?? $usuario->discenteProfile?->orcid_url) }}
                                            </span>
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    @if ($usuario->categoria === 'sec')
                                        <span class="badge rounded-pill text-bg-dark">Secretaria</span>
                                    @elseif ($usuario->categoria === 'docente')
                                        @if ($vinculoDocente?->tipo_vinculo === 'externo')
                                            <span class="badge rounded-pill text-bg-secondary">
                                                <i class="bi bi-globe2 me-1"></i>Docente Externo
                                            </span>
                                        @else
                                            <span class="badge rounded-pill text-bg-primary">
                                                <i class="bi bi-person-badge me-1"></i>Docente
                                            </span>
                                            @if ($vinculoDocente?->categoria_docente)
                                                <div class="small text-muted mt-1">
                                                    {{ ucfirst($vinculoDocente->categoria_docente) }}
                                                </div>
                                            @endif
                                        @endif
                                    @elseif ($usuario->categoria === 'discente')
                                        <span class="badge rounded-pill text-bg-info text-white">
                                            <i class="bi bi-mortarboard me-1"></i>Discente
                                        </span>
                                    @endif
                                </td>

                                <td class="text-muted small">
                                    @if ($usuario->categoria === 'sec')
                                        @forelse ($usuario->programaUsuarios as $vinculo)
                                            <div>{{ $vinculo->programa->sigla ?? $vinculo->programa->nome }}</div>
                                        @empty
                                            Sem programa vinculado
                                        @endforelse
                                    @elseif ($usuario->categoria === 'docente' && $usuario->docenteProfile)
                                        @if ($vinculoDocente)
                                            <div>
                                                <strong>{{ $vinculoDocente->programa->sigla ?? $vinculoDocente->programa->nome }}</strong>
                                                @if ($vinculoDocente->tipo_vinculo === 'externo')
                                                    — <em>{{ $usuario->docenteProfile->instituicao_origem ?? 'Instituição Externa' }}</em>
                                                @else
                                                    — {{ ucfirst($vinculoDocente->tipo_vinculo) }}
                                                @endif
                                            </div>
                                            <div class="mt-1">
                                                @if ($vinculoDocente->pode_orientar)
                                                    <span class="badge bg-light text-dark border me-1">Orienta</span>
                                                @endif
                                                @if ($vinculoDocente->pode_avaliar)
                                                    <span class="badge bg-light text-dark border">Avalia</span>
                                                @endif
                                            </div>
                                        @else
                                            <div>{{ $usuario->docenteProfile->instituicao_origem ?? 'Sem vínculo direto' }}</div>
                                        @endif
                                    @elseif ($usuario->categoria === 'discente' && $usuario->discenteProfile)
                                        @if ($vinculoDiscente)
                                            <div>
                                                <strong>{{ $vinculoDiscente->programaCurso->nome ?? ucfirst($vinculoDiscente->nivel) }}</strong>
                                            </div>
                                            @if ($vinculoDiscente->linhaPesquisa)
                                                <div class="text-truncate" style="max-width: 250px;" title="{{ $vinculoDiscente->linhaPesquisa->nome }}">
                                                    Linha: {{ $vinculoDiscente->linhaPesquisa->nome }}
                                                </div>
                                            @endif
                                            @if ($vinculoDiscente->matricula)
                                                <div>Matrícula: {{ $vinculoDiscente->matricula }}</div>
                                            @endif
                                        @else
                                            Sem programa vinculado
                                        @endif
                                    @else
                                        Sem vínculo
                                    @endif
                                </td>

                                <td>
                                    @if ($usuario->ativo)
                                        <span class="badge rounded-pill text-bg-success">Ativo</span>
                                    @else
                                        <span class="badge rounded-pill text-bg-danger">Inativo</span>
                                    @endif
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2 flex-wrap">

                                        <a href="{{ route('usuarios.edit', $usuario) }}"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Editar usuário">
                                            <i class="bi bi-pencil me-1"></i>Editar
                                        </a>

                                        @if ($usuario->cadastro_status === 'completo' && $usuario->primeiro_acesso)
                                        <form method="POST"
                                            action="{{ route('usuarios.reenviar-acesso', $usuario) }}"
                                            onsubmit="return confirm('Deseja gerar uma nova senha temporária e reenviar o acesso?');">
                                            @csrf

                                            <button type="submit"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Reenviar e-mail de primeiro acesso">
                                                <i class="bi bi-envelope me-1"></i>Reenviar acesso
                                            </button>
                                        </form>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
                                    Nenhum usuário encontrado
                                    @if ($classe !== 'todos')
                                        na categoria <strong>{{ $infoAtual['titulo'] }}</strong>
                                    @endif
                                    @if ($busca)
                                        com o termo "<strong>{{ $busca }}</strong>".
                                    @else
                                        .
                                    @endif
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>