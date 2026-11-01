@use('App\Models\ProducaoPublicacao')
@use('App\Models\ProducaoExtensao')
@use('App\Models\ProducaoPopularizacao')
<x-app-layout>
    @php
        // "Adicionar" numa sugestão do OpenAlex preenche o formulário de publicação.
        $sugerida = $sugestoes->get((int) request('sugestao', -1));
        $tipoOpenAlex = ['article' => 'artigo', 'book' => 'livro', 'book-chapter' => 'capitulo', 'proceedings-article' => 'anais'];
        $pub = [
            'tipo' => old('tipo', $sugerida ? ($tipoOpenAlex[$sugerida['tipo'] ?? ''] ?? 'artigo') : 'artigo'),
            'titulo' => old('titulo', $sugerida['titulo'] ?? ''),
            'ano' => old('ano', $sugerida['ano'] ?? ''),
            'veiculo' => old('veiculo', $sugerida['host_venue'] ?? ''),
            'doi' => old('doi', $sugerida['doi'] ?? ''),
            'openalex_id' => old('openalex_id', $sugerida['id'] ?? ''),
        ];
        $confirmada = $trabalho->producao_confirmada_em !== null;
    @endphp

    <div class="container-fluid" style="max-width: 1000px;">
        <a href="{{ route('meu-trabalho.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Voltar a Meu trabalho
        </a>
        <h1 class="h3 fw-bold mt-2 mb-1" style="color:#00303A;">Produção associada</h1>
        <p class="text-muted mb-3">
            Produtos que saíram da sua dissertação ou tese: publicações, atividades de extensão e de popularização das ciências.
            A banca vê esta lista; a Secretaria confere as publicações na homologação da defesa.
        </p>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($travado)
            <div class="alert alert-info"><i class="bi bi-lock-fill me-2"></i>{{ \App\Http\Controllers\TrabalhoImpactoController::MENSAGEM_TRAVADO }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        @endif

        <div @class(['alert', 'alert-success' => $publicacoes->count() >= $minimo, 'alert-warning' => $publicacoes->count() < $minimo])>
            Seu curso exige pelo menos {{ $minimo }} {{ $minimo === 1 ? 'publicação' : 'publicações' }} para a defesa.
            Você informou {{ $publicacoes->count() }}.
        </div>

        {{-- Publicações --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 fw-bold" style="color:#00303A;"><i class="bi bi-journal-text me-2"></i>Publicações</h2>
                @forelse ($publicacoes as $p)
                    <div class="d-flex justify-content-between align-items-start border-bottom py-2 gap-2">
                        <div>
                            <div class="fw-semibold">{{ $p->titulo }}</div>
                            <div class="small text-muted">
                                {{ ProducaoPublicacao::TIPOS[$p->tipo] }} · {{ $p->veiculo }} · {{ $p->ano }}
                                @if ($p->canal) · {{ ProducaoPublicacao::CANAIS[$p->tipo][$p->canal] ?? $p->canal }} @endif
                                @if ($p->doi) · DOI {{ $p->doi }} @endif
                            </div>
                            <div class="mt-1">@include('partials.selo-openalex', ['publicacao' => $p])</div>
                        </div>
                        @unless ($travado)
                            <form method="POST" action="{{ route('meu-trabalho.producao.destroy', ['publicacao', $p->id]) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                            </form>
                        @endunless
                    </div>
                @empty
                    <p class="text-muted small mb-0">Nenhuma publicação informada.</p>
                @endforelse

                @if ($sugestoes->isNotEmpty() && ! $travado)
                    <h3 class="small fw-semibold mt-3">Encontradas no OpenAlex pelo seu ORCID</h3>
                    <ul class="list-unstyled small mb-0">
                        @foreach ($sugestoes as $i => $obra)
                            <li class="d-flex justify-content-between align-items-center border-bottom py-1 gap-2">
                                <span>{{ $obra['titulo'] ?? '—' }} <span class="text-muted">({{ $obra['ano'] ?? '' }})</span></span>
                                <a href="{{ route('meu-trabalho.producao.index', ['sugestao' => $i]) }}#nova-publicacao" class="btn btn-sm btn-outline-primary">Adicionar</a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @unless ($travado)
                    <form method="POST" action="{{ route('meu-trabalho.producao.store', 'publicacao') }}" id="nova-publicacao" class="row g-2 mt-3">
                        @csrf
                        <input type="hidden" name="openalex_id" value="{{ $pub['openalex_id'] }}">
                        <div class="col-md-3">
                            <label class="form-label small">Tipo</label>
                            <select name="tipo" class="form-select form-select-sm">
                                @foreach (ProducaoPublicacao::TIPOS as $v => $r)
                                    <option value="{{ $v }}" @selected($pub['tipo'] === $v)>{{ $r }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small">Canal (livro, capítulo ou anais)</label>
                            <select name="canal" class="form-select form-select-sm">
                                <option value="">— artigo: não se aplica —</option>
                                @foreach (ProducaoPublicacao::CANAIS as $tipoCanal => $canais)
                                    <optgroup label="{{ ProducaoPublicacao::TIPOS[$tipoCanal] }}">
                                        @foreach ($canais as $v => $r)
                                            <option value="{{ $v }}" @selected(old('canal') === $v)>{{ $r }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Ano</label>
                            <input type="number" name="ano" class="form-control form-control-sm" required value="{{ $pub['ano'] }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">ISSN</label>
                            <input type="text" name="issn" class="form-control form-control-sm" maxlength="20" value="{{ old('issn') }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small">Título</label>
                            <input type="text" name="titulo" class="form-control form-control-sm" maxlength="500" required value="{{ $pub['titulo'] }}">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small">Revista, editora ou evento</label>
                            <input type="text" name="veiculo" class="form-control form-control-sm" maxlength="300" required value="{{ $pub['veiculo'] }}">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small">DOI</label>
                            <input type="text" name="doi" class="form-control form-control-sm" maxlength="255" value="{{ $pub['doi'] }}">
                        </div>
                        <div class="col-12"><button class="btn btn-sm btn-primary">Adicionar publicação</button></div>
                    </form>
                @endunless
            </div>
        </div>

        {{-- Extensão --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 fw-bold" style="color:#00303A;"><i class="bi bi-people me-2"></i>Atividades de extensão</h2>
                @forelse ($extensoes as $e)
                    <div class="d-flex justify-content-between align-items-start border-bottom py-2 gap-2">
                        <div>
                            <div class="fw-semibold">{{ $e->titulo }}</div>
                            <div class="small text-muted">
                                {{ ProducaoExtensao::TIPOS[$e->tipo] }} · {{ $e->parceiro }} · {{ $e->publico }} pessoas · {{ $e->carga_horaria }} h
                                · {{ $e->inicio->format('d/m/Y') }}@if ($e->fim) a {{ $e->fim->format('d/m/Y') }}@endif
                            </div>
                        </div>
                        @unless ($travado)
                            <form method="POST" action="{{ route('meu-trabalho.producao.destroy', ['extensao', $e->id]) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                            </form>
                        @endunless
                    </div>
                @empty
                    <p class="text-muted small mb-0">Nenhuma atividade de extensão informada.</p>
                @endforelse

                @unless ($travado)
                    <form method="POST" action="{{ route('meu-trabalho.producao.store', 'extensao') }}" class="row g-2 mt-3">
                        @csrf
                        <div class="col-md-3">
                            <label class="form-label small">Tipo</label>
                            <select name="tipo" class="form-select form-select-sm">
                                @foreach (ProducaoExtensao::TIPOS as $v => $r)<option value="{{ $v }}">{{ $r }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label small">Título</label>
                            <input type="text" name="titulo" class="form-control form-control-sm" maxlength="500" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small">Comunidade ou parceiro</label>
                            <input type="text" name="parceiro" class="form-control form-control-sm" maxlength="300" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Público (pessoas)</label>
                            <input type="number" name="publico" min="0" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Carga horária</label>
                            <input type="number" name="carga_horaria" min="0" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Registro na pró-reitoria</label>
                            <input type="text" name="registro_proex" maxlength="60" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Início</label>
                            <input type="date" name="inicio" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Fim</label>
                            <input type="date" name="fim" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Produto entregue ao parceiro</label>
                            <input type="text" name="produto_entregue" maxlength="500" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small">Link da evidência (certificado, declaração)</label>
                            <input type="url" name="link" maxlength="500" class="form-control form-control-sm">
                        </div>
                        <div class="col-12"><button class="btn btn-sm btn-primary">Adicionar extensão</button></div>
                    </form>
                @endunless
            </div>
        </div>

        {{-- Popularização --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 fw-bold" style="color:#00303A;"><i class="bi bi-megaphone me-2"></i>Popularização das ciências</h2>
                @forelse ($popularizacoes as $pp)
                    <div class="d-flex justify-content-between align-items-start border-bottom py-2 gap-2">
                        <div>
                            <div class="fw-semibold">{{ $pp->titulo }}</div>
                            <div class="small text-muted">
                                {{ ProducaoPopularizacao::TIPOS[$pp->tipo] }} · {{ $pp->veiculo }} · alcance {{ $pp->alcance }} · {{ $pp->data->format('d/m/Y') }}
                            </div>
                        </div>
                        @unless ($travado)
                            <form method="POST" action="{{ route('meu-trabalho.producao.destroy', ['popularizacao', $pp->id]) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                            </form>
                        @endunless
                    </div>
                @empty
                    <p class="text-muted small mb-0">Nenhuma ação de popularização informada.</p>
                @endforelse

                @unless ($travado)
                    <form method="POST" action="{{ route('meu-trabalho.producao.store', 'popularizacao') }}" class="row g-2 mt-3">
                        @csrf
                        <div class="col-md-3">
                            <label class="form-label small">Tipo</label>
                            <select name="tipo" class="form-select form-select-sm">
                                @foreach (ProducaoPopularizacao::TIPOS as $v => $r)<option value="{{ $v }}">{{ $r }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label small">Título</label>
                            <input type="text" name="titulo" class="form-control form-control-sm" maxlength="500" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small">Veículo ou local</label>
                            <input type="text" name="veiculo" class="form-control form-control-sm" maxlength="300" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Alcance (público, visualizações, tiragem)</label>
                            <input type="number" name="alcance" min="0" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Data</label>
                            <input type="date" name="data" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small">Link ou evidência</label>
                            <input type="url" name="link" maxlength="500" class="form-control form-control-sm">
                        </div>
                        <div class="col-12"><button class="btn btn-sm btn-primary">Adicionar popularização</button></div>
                    </form>
                @endunless
            </div>
        </div>

        {{-- Confirmação --}}
        <div class="card border-0 shadow-sm mb-5">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    @if ($confirmada)
                        <span class="badge bg-success">Confirmada em {{ $trabalho->producao_confirmada_em->format('d/m/Y H:i') }}</span>
                    @else
                        <span class="badge bg-warning text-dark">Não confirmada</span>
                    @endif
                    <div class="small text-muted mt-1">A confirmação é exigida para enviar a defesa à homologação. Qualquer alteração desfaz a confirmação.</div>
                </div>
                @if (! $travado && ! $confirmada)
                    <form method="POST" action="{{ route('meu-trabalho.producao.confirmar') }}">
                        @csrf
                        <button class="btn btn-primary">Confirmo que esta lista está completa</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
