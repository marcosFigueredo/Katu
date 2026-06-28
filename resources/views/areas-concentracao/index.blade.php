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
                    Áreas de Concentração
                </h1>

                <p class="text-muted mb-0">
                    Programa: <strong>{{ $programa->nome }}</strong>
                </p>
            </div>

            <a href="{{ route('programas.areas.create', $programa) }}"
                class="btn text-white fw-semibold"
                style="background-color: #00303A;">
                + Nova área
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-dark">
                        Áreas cadastradas
                    </span>

                    <span class="badge text-bg-light">
                        Total: {{ $programa->areasConcentracao->count() }}
                    </span>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Área de concentração</th>
                                <th>Objetivo</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($programa->areasConcentracao as $area)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">
                                        {{ $area->nome }}
                                    </div>

                                    @if ($area->ativo)
                                    <span class="badge rounded-pill text-bg-success mt-1">
                                        Ativa
                                    </span>
                                    @else
                                    <span class="badge rounded-pill text-bg-danger mt-1">
                                        Inativa
                                    </span>
                                    @endif
                                </td>

                                <td class="text-muted">
                                    {{ $area->objetivo ? Str::limit($area->objetivo, 140) : 'Não informado' }}
                                </td>

                                <td class="text-end pe-4">
                                    <a href="{{ route('areas.linhas.index', $area) }}"
                                        class="btn btn-sm btn-outline-success">
                                        Linhas
                                    </a>

                                    <a href="{{ route('areas-concentracao.edit', $area) }}"
                                        class="btn btn-sm btn-outline-primary ms-1">
                                        Editar
                                    </a>

                                    <form method="POST"
                                        action="{{ route('areas-concentracao.destroy', $area) }}"
                                        class="d-inline"
                                        onsubmit="return confirm('Deseja realmente remover esta área de concentração?');">
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
                                <td colspan="3" class="text-center text-muted py-5">
                                    Nenhuma área de concentração cadastrada para este programa.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <a href="{{ route('programas.index') }}"
                class="btn btn-outline-secondary btn-sm">
                Voltar para Programas
            </a>
        </div>

    </div>
</x-app-layout>