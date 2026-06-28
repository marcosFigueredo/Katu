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
                    Linhas de Pesquisa
                </h1>

                <p class="text-muted mb-0">
                    Programa: <strong>{{ $areaConcentracao->programa->nome }}</strong>
                    <br>
                    Área de concentração: <strong>{{ $areaConcentracao->nome }}</strong>
                </p>
            </div>

            <a href="{{ route('areas.linhas.create', $areaConcentracao) }}"
                class="btn text-white fw-semibold"
                style="background-color: #00303A;">
                + Nova linha
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-dark">
                        Linhas cadastradas
                    </span>

                    <span class="badge text-bg-light">
                        Total: {{ $areaConcentracao->linhasPesquisa->count() }}
                    </span>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Linha de pesquisa</th>
                                <th>Objetivo</th>
                                <th>ODS</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($areaConcentracao->linhasPesquisa as $linha)
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark">
                                            {{ $linha->nome }}
                                        </div>

                                        @if ($linha->ativo)
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
                                        {{ $linha->objetivo ? Str::limit($linha->objetivo, 140) : 'Não informado' }}
                                    </td>

                                    <td>
                                        @forelse (($linha->ods ?? []) as $ods)
                                            <span class="badge rounded-pill text-bg-secondary">
                                                ODS {{ $ods }}
                                            </span>
                                        @empty
                                            <span class="text-muted small">
                                                Não informado
                                            </span>
                                        @endforelse
                                    </td>

                                    <td class="text-end pe-4">
                                        <a href="{{ route('linhas-pesquisa.edit', $linha) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            Editar
                                        </a>

                                        <form method="POST"
                                            action="{{ route('linhas-pesquisa.destroy', $linha) }}"
                                            class="d-inline"
                                            onsubmit="return confirm('Deseja realmente remover esta linha de pesquisa?');">
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
                                        Nenhuma linha de pesquisa cadastrada para esta área.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <a href="{{ route('programas.areas.index', $areaConcentracao->programa) }}"
                class="btn btn-outline-secondary btn-sm">
                Voltar para Áreas de Concentração
            </a>
        </div>

    </div>
</x-app-layout>