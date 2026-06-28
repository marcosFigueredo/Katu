<x-app-layout>
    <div class="container-fluid">

        @if (session('status'))
        <div class="alert alert-success shadow-sm">
            {{ session('status') }}
        </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 fw-bold text-dark">
                    Usuários
                </h1>

                <p class="text-muted mb-0">
                    Cadastro e gerenciamento de secretaria, docentes e discentes.
                </p>
            </div>

            <a href="{{ route('usuarios.create') }}"
                class="btn text-white fw-semibold"
                style="background-color: #00303A;">
                + Novo usuário
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-dark">
                        Usuários cadastrados
                    </span>

                    <span class="badge text-bg-light">
                        Total: {{ $usuarios->count() }}
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
                                <th>Categoria</th>
                                <th>Vínculos</th>
                                <th>Situação</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($usuarios as $usuario)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">
                                        {{ $usuario->name }}
                                    </div>

                                    @if ($usuario->cadastro_status === 'pendente')
                                    <span class="badge rounded-pill text-bg-warning mt-1">
                                        Cadastro pendente
                                    </span>
                                    @else
                                    <span class="badge rounded-pill text-bg-success mt-1">
                                        Cadastro completo
                                    </span>
                                    @endif

                                    @if ($usuario->primeiro_acesso)
                                    <span class="badge rounded-pill text-bg-secondary mt-1">
                                        Primeiro acesso pendente
                                    </span>
                                    @endif
                                </td>

                                <td class="text-muted">
                                    {{ $usuario->email }}
                                </td>

                                <td>
                                    @if ($usuario->categoria === 'sec')
                                    <span class="badge rounded-pill text-bg-dark">Secretaria</span>
                                    @elseif ($usuario->categoria === 'docente')
                                    <span class="badge rounded-pill text-bg-primary">Docente</span>
                                    @elseif ($usuario->categoria === 'discente')
                                    <span class="badge rounded-pill text-bg-info">Discente</span>
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
                                    @forelse ($usuario->docenteProfile->vinculosPrograma as $vinculo)
                                    <div>
                                        {{ $vinculo->programa->sigla ?? $vinculo->programa->nome }}
                                        —
                                        {{ ucfirst($vinculo->tipo_vinculo) }}
                                    </div>
                                    @empty
                                    Sem programa vinculado
                                    @endforelse
                                    @elseif ($usuario->categoria === 'discente' && $usuario->discenteProfile)
                                    @forelse ($usuario->discenteProfile->vinculosPrograma as $vinculo)
                                    <div>
                                        {{ $vinculo->programa->sigla ?? $vinculo->programa->nome }}
                                        —
                                        {{ ucfirst($vinculo->nivel) }}
                                    </div>
                                    @empty
                                    Sem programa vinculado
                                    @endforelse
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
                                            class="btn btn-sm btn-outline-primary">
                                            Editar
                                        </a>

                                        @if ($usuario->cadastro_status === 'completo' && $usuario->primeiro_acesso)
                                        <form method="POST"
                                            action="{{ route('usuarios.reenviar-acesso', $usuario) }}"
                                            onsubmit="return confirm('Deseja gerar uma nova senha temporária e reenviar o acesso?');">
                                            @csrf

                                            <button type="submit"
                                                class="btn btn-sm btn-outline-secondary">
                                                Reenviar acesso
                                            </button>
                                        </form>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    Nenhum usuário cadastrado.
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