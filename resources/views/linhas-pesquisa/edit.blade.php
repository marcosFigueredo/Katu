<x-app-layout>
    <div class="container-fluid">

        <div class="mb-4">
            <h1 class="h3 mb-1 fw-bold text-dark">
                Editar Linha de Pesquisa
            </h1>

            <p class="text-muted mb-0">
                Programa: <strong>{{ $linhaPesquisa->areaConcentracao->programa->nome }}</strong>
                <br>
                Área de concentração: <strong>{{ $linhaPesquisa->areaConcentracao->nome }}</strong>
            </p>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">

                <form method="POST" action="{{ route('linhas-pesquisa.update', $linhaPesquisa) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="nome" class="form-label fw-semibold">
                            Nome da linha de pesquisa
                        </label>

                        <input type="text"
                            id="nome"
                            name="nome"
                            class="form-control"
                            value="{{ old('nome', $linhaPesquisa->nome) }}"
                            required>

                        @error('nome')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="objetivo" class="form-label fw-semibold">
                            Objetivo da linha de pesquisa
                        </label>

                        <textarea id="objetivo"
                            name="objetivo"
                            rows="5"
                            class="form-control">{{ old('objetivo', $linhaPesquisa->objetivo) }}</textarea>

                        @error('objetivo')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            ODS relacionadas à linha de pesquisa
                        </label>

                        @php
                            $odsSelecionadas = old('ods', $linhaPesquisa->ods ?? []);
                        @endphp

                        <div class="row g-2">
                            @for ($i = 1; $i <= 17; $i++)
                                <div class="col-6 col-md-3 col-lg-2">
                                    <label class="form-check border rounded p-2 h-100">
                                        <input class="form-check-input"
                                            type="checkbox"
                                            name="ods[]"
                                            value="{{ $i }}"
                                            {{ in_array($i, $odsSelecionadas) ? 'checked' : '' }}>

                                        <span class="form-check-label">
                                            ODS {{ $i }}
                                        </span>
                                    </label>
                                </div>
                            @endfor
                        </div>

                        @error('ods')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror

                        @error('ods.*')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('areas.linhas.index', $linhaPesquisa->areaConcentracao) }}"
                            class="btn btn-outline-secondary">
                            Cancelar
                        </a>

                        <button type="submit"
                            class="btn text-white fw-semibold"
                            style="background-color: #00303A;">
                            Atualizar linha
                        </button>
                    </div>
                </form>

            </div>
        </div>

    </div>
</x-app-layout>