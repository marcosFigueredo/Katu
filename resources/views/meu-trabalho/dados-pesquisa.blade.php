@use('App\Models\TrabalhoDadosPesquisa')
<x-app-layout>
    @php
        $codigo = old('codigo', $dados->codigo);
        $disponibilidade = old('dados_disponibilidade', $dados->dados_disponibilidade);
        $tipos = old('dados_tipos', $dados->dados_tipos ?? []);
        $humanos = old('seres_humanos', $dados->exists ? (int) $dados->seres_humanos : null);
        $tcle = old('tcle', $dados->tcle);
        $animais = old('animais', $dados->exists ? (int) $dados->animais : null);
    @endphp

    <div class="container-fluid" style="max-width: 900px;">
        <a href="{{ route('meu-trabalho.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Voltar a Meu trabalho
        </a>
        <h1 class="h3 fw-bold mt-2 mb-1" style="color:#00303A;">Dados da pesquisa</h1>
        <p class="text-muted mb-3">
            Código, dados e ética da sua pesquisa. É preciso preencher antes de enviar o pedido à homologação;
            os números de ética aparecem para a banca.
        </p>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('meu-trabalho.dados-pesquisa.update') }}" class="card border-0 shadow-sm card-body">
            @csrf
            @method('PUT')

            <fieldset class="mb-4">
                <legend class="fs-6 fw-semibold">1. Código (software)</legend>
                <p class="form-text mt-0">A pesquisa tem código próprio associado?</p>
                @foreach (TrabalhoDadosPesquisa::CODIGO as $valor => $rotulo)
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="codigo" id="codigo-{{ $valor }}" value="{{ $valor }}" required @checked($codigo === $valor)>
                        <label class="form-check-label" for="codigo-{{ $valor }}">{{ $rotulo }}</label>
                    </div>
                @endforeach
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label for="codigo_link" class="form-label small">Link do repositório (se público)</label>
                        <input id="codigo_link" name="codigo_link" type="url" maxlength="500" placeholder="https://github.com/..."
                               class="form-control form-control-sm @error('codigo_link') is-invalid @enderror" value="{{ old('codigo_link', $dados->codigo_link) }}">
                    </div>
                    <div class="col-md-6">
                        <label for="codigo_justificativa" class="form-label small">Justificativa (se não pode ser compartilhado)</label>
                        <textarea id="codigo_justificativa" name="codigo_justificativa" rows="2" maxlength="2000"
                                  class="form-control form-control-sm @error('codigo_justificativa') is-invalid @enderror">{{ old('codigo_justificativa', $dados->codigo_justificativa) }}</textarea>
                    </div>
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend class="fs-6 fw-semibold">2. Dados</legend>
                <p class="form-text mt-0">Onde estão os dados da pesquisa?</p>
                @foreach (TrabalhoDadosPesquisa::DADOS_DISPONIBILIDADE as $valor => $rotulo)
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="dados_disponibilidade" id="disp-{{ $valor }}" value="{{ $valor }}" required @checked($disponibilidade === $valor)>
                        <label class="form-check-label" for="disp-{{ $valor }}">{{ $rotulo }}</label>
                    </div>
                @endforeach
                <p class="form-text mb-1 mt-2">Que dados foram usados (pode marcar mais de um; não precisa se "não se aplica")?</p>
                @foreach (TrabalhoDadosPesquisa::DADOS_TIPOS as $valor => $rotulo)
                    <div class="form-check">
                        <input class="form-check-input @error('dados_tipos') is-invalid @enderror" type="checkbox" name="dados_tipos[]" id="tipo-{{ $valor }}" value="{{ $valor }}" @checked(in_array($valor, $tipos, true))>
                        <label class="form-check-label" for="tipo-{{ $valor }}">{{ $rotulo }}</label>
                    </div>
                @endforeach
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label for="dados_link" class="form-label small">Link ou DOI do repositório (se público)</label>
                        <input id="dados_link" name="dados_link" type="url" maxlength="500" placeholder="https://doi.org/..."
                               class="form-control form-control-sm @error('dados_link') is-invalid @enderror" value="{{ old('dados_link', $dados->dados_link) }}">
                    </div>
                    <div class="col-md-6">
                        <label for="dados_justificativa" class="form-label small">Justificativa (se não podem ser compartilhados)</label>
                        <textarea id="dados_justificativa" name="dados_justificativa" rows="2" maxlength="2000"
                                  class="form-control form-control-sm @error('dados_justificativa') is-invalid @enderror">{{ old('dados_justificativa', $dados->dados_justificativa) }}</textarea>
                    </div>
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend class="fs-6 fw-semibold">3. Seres humanos</legend>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="seres_humanos" id="humanos-0" value="0" required @checked((string) $humanos === '0')>
                    <label class="form-check-label" for="humanos-0">Não, a pesquisa não envolveu seres humanos</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="seres_humanos" id="humanos-1" value="1" @checked((string) $humanos === '1')>
                    <label class="form-check-label" for="humanos-1">Sim, com aprovação do Comitê de Ética em Pesquisa</label>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label for="caae" class="form-label small">CAAE (Plataforma Brasil)</label>
                        <input id="caae" name="caae" maxlength="20" placeholder="00000000.0.0000.0000"
                               class="form-control form-control-sm @error('caae') is-invalid @enderror" value="{{ old('caae', $dados->caae) }}">
                    </div>
                    <div class="col-md-6">
                        <label for="parecer_numero" class="form-label small">Número do parecer</label>
                        <input id="parecer_numero" name="parecer_numero" maxlength="30"
                               class="form-control form-control-sm @error('parecer_numero') is-invalid @enderror" value="{{ old('parecer_numero', $dados->parecer_numero) }}">
                    </div>
                </div>
                <div class="mt-2">
                    @foreach (TrabalhoDadosPesquisa::TCLE as $valor => $rotulo)
                        <div class="form-check">
                            <input class="form-check-input @error('tcle') is-invalid @enderror" type="radio" name="tcle" id="tcle-{{ $valor }}" value="{{ $valor }}" @checked($tcle === $valor)>
                            <label class="form-check-label" for="tcle-{{ $valor }}">{{ $rotulo }}</label>
                        </div>
                    @endforeach
                    <label for="tcle_justificativa" class="form-label small mt-2">Justificativa da dispensa</label>
                    <textarea id="tcle_justificativa" name="tcle_justificativa" rows="2" maxlength="2000"
                              class="form-control form-control-sm @error('tcle_justificativa') is-invalid @enderror">{{ old('tcle_justificativa', $dados->tcle_justificativa) }}</textarea>
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend class="fs-6 fw-semibold">4. Animais</legend>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="animais" id="animais-0" value="0" required @checked((string) $animais === '0')>
                    <label class="form-check-label" for="animais-0">Não, a pesquisa não envolveu animais</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="animais" id="animais-1" value="1" @checked((string) $animais === '1')>
                    <label class="form-check-label" for="animais-1">Sim, com aprovação da Comissão de Ética no Uso de Animais</label>
                </div>
                <div class="col-md-6 mt-2">
                    <label for="ceua_protocolo" class="form-label small">Número do protocolo</label>
                    <input id="ceua_protocolo" name="ceua_protocolo" maxlength="60"
                           class="form-control form-control-sm @error('ceua_protocolo') is-invalid @enderror" value="{{ old('ceua_protocolo', $dados->ceua_protocolo) }}">
                </div>
            </fieldset>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Salvar
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
