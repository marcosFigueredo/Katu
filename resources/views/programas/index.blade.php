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
                    Programas
                </h1>

                <p class="text-muted mb-0">
                    Cadastro e gerenciamento dos programas de pós-graduação, áreas de concentração e linhas de pesquisa.
                </p>
            </div>

            <a href="{{ route('programas.create') }}"
                class="btn text-white fw-semibold"
                style="background-color: #00303A;">
                + Novo programa
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-dark">
                        Programas cadastrados
                    </span>

                    <span class="badge text-bg-light">
                        Total: {{ $programas->count() }}
                    </span>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Programa</th>
                                <th>Níveis</th>
                                <th>Situação</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($programas as $programa)
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark">
                                            {{ $programa->nome }}
                                        </div>

                                        <div class="small text-muted">
                                            {{ $programa->sigla ?? 'Sem sigla' }}
                                        </div>

                                        @if ($programa->area_avaliacao)
                                            <div class="small text-muted mt-1">
                                                Área de avaliação: {{ $programa->area_avaliacao }}
                                            </div>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($programa->oferta_mestrado)
                                            <span class="badge rounded-pill text-bg-secondary">
                                                Mestrado
                                            </span>
                                        @endif

                                        @if ($programa->oferta_doutorado)
                                            <span class="badge rounded-pill text-bg-secondary">
                                                Doutorado
                                            </span>
                                        @endif

                                        @if (! $programa->oferta_mestrado && ! $programa->oferta_doutorado)
                                            <span class="text-muted small">
                                                Não informado
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($programa->ativo)
                                            <span class="badge rounded-pill text-bg-success">
                                                Ativo
                                            </span>
                                        @else
                                            <span class="badge rounded-pill text-bg-danger">
                                                Inativo
                                            </span>
                                        @endif
                                    </td>

                                    <td class="text-end pe-4">
                                        <a href="{{ route('programas.areas.index', $programa) }}"
                                            class="btn btn-sm btn-outline-success">
                                            Áreas
                                        </a>

                                        <a href="{{ route('programas.edit', $programa) }}"
                                            class="btn btn-sm btn-outline-primary ms-1">
                                            Editar
                                        </a>

                                        <form method="POST"
                                            action="{{ route('programas.destroy', $programa) }}"
                                            class="d-inline"
                                            onsubmit="return confirm('Deseja realmente remover este programa?');">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                class="btn btn-sm btn-outline-danger ms-1">
                                                Remover
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-5">
                                        Nenhum programa cadastrado.
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