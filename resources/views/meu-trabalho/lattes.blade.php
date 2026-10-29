<x-app-layout>
    <div class="container-fluid" style="max-width: 900px;">
        <a href="{{ route('meu-trabalho.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Voltar a Meu trabalho
        </a>
        <h1 class="h3 fw-bold mt-2 mb-1" style="color:#00303A;">Dados para o Lattes</h1>
        <p class="text-muted mb-3">
            Os membros da sua banca usam estes dados para registrar a participação no Currículo Lattes.
            Título, ano, palavras-chave e participantes vêm do sistema; aqui você completa o resto.
        </p>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('meu-trabalho.lattes.update') }}" class="card border-0 shadow-sm card-body">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="busca-area" class="form-label fw-semibold">Áreas do CNPq</label>
                <input type="search" id="busca-area" class="form-control form-control-sm mb-2" placeholder="Filtrar áreas (ex.: saúde coletiva)">
                <select id="areas" name="areas[]" multiple size="10" class="form-select">
                    @foreach ($areas as $area)
                        @php($escolhida = in_array($area->id, old('areas', $selecionadas)))
                        <option value="{{ $area->id }}" @selected($escolhida)>
                            {{ $area->caminho() }}{{ $area->ativo ? '' : ' (inativa)' }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Selecione uma ou mais (Ctrl/⌘ + clique).</div>
            </div>

            <div class="mb-3">
                <label for="setor_cnae_id" class="form-label fw-semibold">Setor de aplicação (CNAE) — se houver</label>
                <select id="setor_cnae_id" name="setor_cnae_id" class="form-select">
                    <option value="">— Nenhum —</option>
                    @foreach ($setores as $setor)
                        <option value="{{ $setor->id }}" @selected((int) old('setor_cnae_id', $trabalho->setor_cnae_id) === $setor->id)>
                            {{ $setor->codigo }} — {{ $setor->nome }}{{ $setor->ativo ? '' : ' (inativo)' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label for="titulo_en" class="form-label fw-semibold">Título em inglês</label>
                <input id="titulo_en" name="titulo_en" maxlength="500" class="form-control" value="{{ old('titulo_en', $tituloEn) }}">
                @if (! $trabalho->titulo_en && $tituloEn)
                    <div class="form-text">Preenchido a partir do seu projeto LaTeX; confira e salve.</div>
                @endif
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="outras_informacoes" class="form-label fw-semibold">Outras informações</label>
                    <textarea id="outras_informacoes" name="outras_informacoes" rows="4" maxlength="5000" class="form-control">{{ old('outras_informacoes', $trabalho->outras_informacoes) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label for="outras_informacoes_en" class="form-label fw-semibold">Other information (em inglês)</label>
                    <textarea id="outras_informacoes_en" name="outras_informacoes_en" rows="4" maxlength="5000" class="form-control">{{ old('outras_informacoes_en', $trabalho->outras_informacoes_en) }}</textarea>
                </div>
            </div>

            <div class="mb-3">
                <span class="form-label fw-semibold d-block">Visibilidade</span>
                @php($visibilidade = old('visibilidade', $trabalho->visibilidade ?? 'publica'))
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="visibilidade" id="vis-publica" value="publica" @checked($visibilidade === 'publica')>
                    <label class="form-check-label" for="vis-publica">Pública</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="visibilidade" id="vis-privada" value="privada" @checked($visibilidade === 'privada')>
                    <label class="form-check-label" for="vis-privada">Privada</label>
                </div>
            </div>

            <div><button class="btn btn-primary">Salvar</button></div>
        </form>
    </div>

    <script>
        document.getElementById('busca-area').addEventListener('input', function () {
            const termo = this.value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
            for (const opcao of document.getElementById('areas').options) {
                const texto = opcao.text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
                opcao.hidden = termo !== '' && ! texto.includes(termo) && ! opcao.selected;
            }
        });
    </script>
</x-app-layout>
