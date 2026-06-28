<x-app-layout>
    <div class="container-fluid">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('baremas.index') }}" class="text-decoration-none">Baremas</a>
                </li>
                <li class="breadcrumb-item active">{{ $barema->nome }}</li>
            </ol>
        </nav>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════════
             BLOCO 1 — Dados básicos
        ══════════════════════════════════════════════════════════ --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0">
                    <i class="bi bi-clipboard2-check-fill me-2" style="color:#00303A;"></i>Dados do Barema
                </h6>
                @if ($barema->ativo)
                    <span class="badge text-bg-success">Ativo</span>
                @else
                    <span class="badge text-bg-secondary">Inativo</span>
                @endif
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('baremas.update', $barema) }}">
                    @csrf @method('PUT')

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="nome" class="form-label fw-semibold">Nome</label>
                            <input type="text" id="nome" name="nome"
                                   value="{{ old('nome', $barema->nome) }}"
                                   class="form-control @error('nome') is-invalid @enderror" required>
                            @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-sm-4">
                            <label for="tipo" class="form-label fw-semibold">Tipo</label>
                            <select id="tipo" name="tipo"
                                    class="form-select @error('tipo') is-invalid @enderror" required>
                                <option value="qualificacao"              {{ old('tipo', $barema->tipo) === 'qualificacao'              ? 'selected' : '' }}>Qualificação</option>
                                <option value="defesa"                    {{ old('tipo', $barema->tipo) === 'defesa'                    ? 'selected' : '' }}>Defesa</option>
                                <option value="qualificacao_confidencial" {{ old('tipo', $barema->tipo) === 'qualificacao_confidencial' ? 'selected' : '' }}>Qualificação Confidencial</option>
                                <option value="defesa_confidencial"       {{ old('tipo', $barema->tipo) === 'defesa_confidencial'       ? 'selected' : '' }}>Defesa Confidencial</option>
                            </select>
                            @error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-sm-4">
                            <label for="nivel" class="form-label fw-semibold">Nível</label>
                            <select id="nivel" name="nivel"
                                    class="form-select @error('nivel') is-invalid @enderror" required>
                                <option value="ambos"    {{ old('nivel', $barema->nivel) === 'ambos'    ? 'selected' : '' }}>Mestrado e Doutorado</option>
                                <option value="mestrado" {{ old('nivel', $barema->nivel) === 'mestrado' ? 'selected' : '' }}>Mestrado</option>
                                <option value="doutorado"{{ old('nivel', $barema->nivel) === 'doutorado'? 'selected' : '' }}>Doutorado</option>
                            </select>
                            @error('nivel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-sm-4">
                            <label for="versao" class="form-label fw-semibold">Versão</label>
                            <input type="text" id="versao" name="versao"
                                   value="{{ old('versao', $barema->versao) }}"
                                   class="form-control @error('versao') is-invalid @enderror" required>
                            @error('versao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label for="descricao" class="form-label fw-semibold">
                                Descrição <span class="fw-normal text-muted">(opcional)</span>
                            </label>
                            <textarea id="descricao" name="descricao" rows="2"
                                      class="form-control @error('descricao') is-invalid @enderror">{{ old('descricao', $barema->descricao) }}</textarea>
                            @error('descricao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox"
                                       id="ativo" name="ativo" value="1"
                                       {{ old('ativo', $barema->ativo) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="ativo">
                                    Barema ativo (em uso)
                                </label>
                            </div>
                            <div class="form-text">
                                Apenas um barema ativo por tipo/nível é usado nas bancas. Ative somente após definir todas as seções e itens.
                            </div>
                        </div>

                        <div class="col-12 pt-1 border-top mt-2">
                            <button type="submit" class="btn btn-primary fw-semibold">
                                <i class="bi bi-floppy-fill me-2"></i>Salvar alterações
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             BLOCO 2 — Seções e Itens
        ══════════════════════════════════════════════════════════ --}}
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="fw-bold mb-0">
                <i class="bi bi-list-ul me-2" style="color:#00303A;"></i>
                Seções e Itens
                <span class="badge text-bg-light border ms-2">
                    {{ $barema->secoes->count() }} seção(ões)
                </span>
            </h6>
            <button class="btn btn-sm btn-outline-primary fw-semibold"
                    data-bs-toggle="modal" data-bs-target="#modalNovaSecao">
                <i class="bi bi-plus-lg me-1"></i>Nova seção
            </button>
        </div>

        @if ($barema->secoes->isEmpty())
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body text-center py-4">
                    <p class="text-muted mb-2">Nenhuma seção cadastrada ainda.</p>
                    <button class="btn btn-outline-primary btn-sm"
                            data-bs-toggle="modal" data-bs-target="#modalNovaSecao">
                        <i class="bi bi-plus-lg me-1"></i>Adicionar primeira seção
                    </button>
                </div>
            </div>
        @else
            <div class="accordion mb-4" id="accordionSecoes">
                @foreach ($barema->secoes as $secao)
                    <div class="accordion-item border-0 shadow-sm mb-2">
                        <h2 class="accordion-header">
                            <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }} fw-semibold"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#secao{{ $secao->id }}">
                                <span class="badge text-bg-secondary me-2">{{ $secao->ordem }}</span>
                                {{ $secao->nome }}
                                <span class="badge text-bg-light border ms-2 fw-normal">
                                    peso: {{ number_format($secao->peso, 2) }}
                                </span>
                                @if (!$secao->ativo)
                                    <span class="badge text-bg-warning ms-2 fw-normal">Inativa</span>
                                @endif
                                <span class="badge text-bg-light border ms-2 fw-normal">
                                    {{ $secao->itens->count() }} item(ns)
                                </span>
                            </button>
                        </h2>
                        <div id="secao{{ $secao->id }}"
                             class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                             data-bs-parent="#accordionSecoes">
                            <div class="accordion-body pt-0">

                                {{-- Ações da seção --}}
                                <div class="d-flex gap-2 mb-3 pb-2 border-bottom">
                                    <button class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditarSecao"
                                            data-id="{{ $secao->id }}"
                                            data-nome="{{ $secao->nome }}"
                                            data-descricao="{{ $secao->descricao }}"
                                            data-peso="{{ $secao->peso }}"
                                            data-ordem="{{ $secao->ordem }}"
                                            data-ativo="{{ $secao->ativo ? '1' : '0' }}"
                                            data-action="{{ route('barema-secoes.update', $secao) }}">
                                        <i class="bi bi-pencil-fill me-1"></i>Editar seção
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalConfirmarExclusao"
                                            data-action="{{ route('barema-secoes.destroy', $secao) }}"
                                            data-label="a seção &quot;{{ $secao->nome }}&quot; e todos os seus itens">
                                        <i class="bi bi-trash3-fill me-1"></i>Excluir seção
                                    </button>
                                </div>

                                @if ($secao->descricao)
                                    <p class="text-muted small mb-3">{{ $secao->descricao }}</p>
                                @endif

                                {{-- Itens da seção --}}
                                @if ($secao->itens->isNotEmpty())
                                    <div class="table-responsive mb-3">
                                        <table class="table table-sm table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:32px;">#</th>
                                                    <th>Item</th>
                                                    <th class="text-center" style="width:90px;">Nota máx.</th>
                                                    <th class="text-center" style="width:70px;">Peso</th>
                                                    <th class="text-center" style="width:80px;">Obrig.</th>
                                                    <th class="text-center" style="width:70px;">Status</th>
                                                    <th style="width:90px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($secao->itens as $item)
                                                    <tr>
                                                        <td class="text-muted small">{{ $item->ordem }}</td>
                                                        <td>
                                                            <div class="fw-semibold text-dark small">{{ $item->nome }}</div>
                                                            @if ($item->descricao)
                                                                <div class="text-muted" style="font-size:12px;">
                                                                    {{ Str::limit($item->descricao, 80) }}
                                                                </div>
                                                            @endif
                                                            @if ($item->indicador_capes)
                                                                <span class="badge text-bg-light border" style="font-size:11px;">
                                                                    CAPES: {{ $item->indicador_capes }}
                                                                </span>
                                                            @endif
                                                        </td>
                                                        <td class="text-center fw-semibold">
                                                            {{ number_format($item->nota_maxima, 2) }}
                                                        </td>
                                                        <td class="text-center text-muted small">
                                                            {{ number_format($item->peso, 2) }}
                                                        </td>
                                                        <td class="text-center">
                                                            @if ($item->obrigatorio)
                                                                <i class="bi bi-check-circle-fill text-success"></i>
                                                            @else
                                                                <i class="bi bi-dash-circle text-muted"></i>
                                                            @endif
                                                        </td>
                                                        <td class="text-center">
                                                            @if ($item->ativo)
                                                                <span class="badge text-bg-success">Ativo</span>
                                                            @else
                                                                <span class="badge text-bg-secondary">Inativo</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-end">
                                                            <button class="btn btn-xs btn-outline-secondary me-1 py-0 px-1"
                                                                    title="Editar item"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#modalEditarItem"
                                                                    data-id="{{ $item->id }}"
                                                                    data-nome="{{ $item->nome }}"
                                                                    data-descricao="{{ $item->descricao }}"
                                                                    data-nota_maxima="{{ $item->nota_maxima }}"
                                                                    data-peso="{{ $item->peso }}"
                                                                    data-obrigatorio="{{ $item->obrigatorio ? '1' : '0' }}"
                                                                    data-indicador_capes="{{ $item->indicador_capes }}"
                                                                    data-ordem="{{ $item->ordem }}"
                                                                    data-ativo="{{ $item->ativo ? '1' : '0' }}"
                                                                    data-action="{{ route('barema-itens.update', $item) }}">
                                                                <i class="bi bi-pencil-fill"></i>
                                                            </button>
                                                            <button class="btn btn-xs btn-outline-danger py-0 px-1"
                                                                    title="Excluir item"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#modalConfirmarExclusao"
                                                                    data-action="{{ route('barema-itens.destroy', $item) }}"
                                                                    data-label="o item &quot;{{ $item->nome }}&quot;">
                                                                <i class="bi bi-trash3-fill"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif

                                {{-- Adicionar item --}}
                                <button class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalNovoItem"
                                        data-secao-id="{{ $secao->id }}"
                                        data-secao-nome="{{ $secao->nome }}"
                                        data-action="{{ route('barema-secoes.itens.store', $secao) }}"
                                        data-proximo-ordem="{{ $secao->itens->count() + 1 }}">
                                    <i class="bi bi-plus-lg me-1"></i>Adicionar item
                                </button>

                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    </div>

    {{-- ════════════════════════════════════════════════════════════
         MODAIS
    ════════════════════════════════════════════════════════════ --}}

    {{-- Modal: Nova Seção --}}
    <div class="modal fade" id="modalNovaSecao" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('baremas.secoes.store', $barema) }}">
                    @csrf
                    <div class="modal-header">
                        <h6 class="modal-title fw-bold">Nova Seção</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nome da seção</label>
                            <input type="text" name="nome" class="form-control"
                                   placeholder="Ex.: Apresentação e Comunicação" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Descrição <span class="fw-normal text-muted">(opcional)</span>
                            </label>
                            <textarea name="descricao" rows="2" class="form-control"
                                      placeholder="Critérios avaliados nesta seção…"></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Peso</label>
                                <input type="number" name="peso" step="0.01" min="0" value="1.00"
                                       class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Ordem</label>
                                <input type="number" name="ordem" min="1"
                                       value="{{ $barema->secoes->count() + 1 }}"
                                       class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-semibold">
                            <i class="bi bi-plus-lg me-1"></i>Adicionar seção
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal: Editar Seção --}}
    <div class="modal fade" id="modalEditarSecao" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="form-editar-secao" action="">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h6 class="modal-title fw-bold">Editar Seção</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nome da seção</label>
                            <input type="text" name="nome" id="es-nome" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Descrição <span class="fw-normal text-muted">(opcional)</span>
                            </label>
                            <textarea name="descricao" id="es-descricao" rows="2" class="form-control"></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Peso</label>
                                <input type="number" name="peso" id="es-peso" step="0.01" min="0"
                                       class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Ordem</label>
                                <input type="number" name="ordem" id="es-ordem" min="1"
                                       class="form-control" required>
                            </div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox"
                                   id="es-ativo" name="ativo" value="1">
                            <label class="form-check-label" for="es-ativo">Seção ativa</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-semibold">
                            <i class="bi bi-floppy-fill me-1"></i>Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal: Novo Item --}}
    <div class="modal fade" id="modalNovoItem" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="form-novo-item" action="">
                    @csrf
                    <div class="modal-header">
                        <h6 class="modal-title fw-bold">
                            Novo Item — <span id="ni-secao-nome" class="fw-normal text-muted"></span>
                        </h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nome do critério</label>
                            <input type="text" name="nome" class="form-control"
                                   placeholder="Ex.: Clareza na apresentação dos objetivos" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Descrição / orientação para o avaliador <span class="fw-normal text-muted">(opcional)</span>
                            </label>
                            <textarea name="descricao" rows="2" class="form-control"
                                      placeholder="Explique o que o avaliador deve observar…"></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-3">
                                <label class="form-label fw-semibold">Nota máxima</label>
                                <input type="number" name="nota_maxima" step="0.01" min="0"
                                       value="10.00" class="form-control" required>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label fw-semibold">Peso</label>
                                <input type="number" name="peso" step="0.01" min="0"
                                       value="1.00" class="form-control" required>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label fw-semibold">Ordem</label>
                                <input type="number" name="ordem" id="ni-ordem" min="1"
                                       class="form-control" required>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label fw-semibold">Indicador CAPES</label>
                                <input type="text" name="indicador_capes" class="form-control"
                                       placeholder="Opcional">
                            </div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox"
                                   id="ni-obrigatorio" name="obrigatorio" value="1" checked>
                            <label class="form-check-label" for="ni-obrigatorio">Item obrigatório</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-semibold">
                            <i class="bi bi-plus-lg me-1"></i>Adicionar item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal: Editar Item --}}
    <div class="modal fade" id="modalEditarItem" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="form-editar-item" action="">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h6 class="modal-title fw-bold">Editar Item</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nome do critério</label>
                            <input type="text" name="nome" id="ei-nome" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Descrição <span class="fw-normal text-muted">(opcional)</span>
                            </label>
                            <textarea name="descricao" id="ei-descricao" rows="2" class="form-control"></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-3">
                                <label class="form-label fw-semibold">Nota máxima</label>
                                <input type="number" name="nota_maxima" id="ei-nota_maxima"
                                       step="0.01" min="0" class="form-control" required>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label fw-semibold">Peso</label>
                                <input type="number" name="peso" id="ei-peso"
                                       step="0.01" min="0" class="form-control" required>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label fw-semibold">Ordem</label>
                                <input type="number" name="ordem" id="ei-ordem"
                                       min="1" class="form-control" required>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label fw-semibold">Indicador CAPES</label>
                                <input type="text" name="indicador_capes" id="ei-indicador_capes"
                                       class="form-control" placeholder="Opcional">
                            </div>
                        </div>
                        <div class="d-flex gap-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox"
                                       id="ei-obrigatorio" name="obrigatorio" value="1">
                                <label class="form-check-label" for="ei-obrigatorio">Obrigatório</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox"
                                       id="ei-ativo" name="ativo" value="1">
                                <label class="form-check-label" for="ei-ativo">Ativo</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-semibold">
                            <i class="bi bi-floppy-fill me-1"></i>Salvar item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal: Confirmar Exclusão (genérico) --}}
    <div class="modal fade" id="modalConfirmarExclusao" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title fw-bold text-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmar exclusão
                    </h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-1">Tem certeza que deseja excluir <span id="excluir-label" class="fw-semibold"></span>?</p>
                    <p class="text-danger small mb-0">
                        <i class="bi bi-exclamation-circle me-1"></i>Esta ação não pode ser desfeita.
                    </p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <form method="POST" id="form-excluir-generico">
                        @csrf @method('DELETE')
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger btn-sm ms-2">Excluir</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         JavaScript para popular modais
    ════════════════════════════════════════════════════════════ --}}
    <script>
        // Editar Seção
        document.getElementById('modalEditarSecao').addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('form-editar-secao').action = btn.dataset.action;
            document.getElementById('es-nome').value            = btn.dataset.nome;
            document.getElementById('es-descricao').value       = btn.dataset.descricao || '';
            document.getElementById('es-peso').value            = btn.dataset.peso;
            document.getElementById('es-ordem').value           = btn.dataset.ordem;
            document.getElementById('es-ativo').checked         = btn.dataset.ativo === '1';
        });

        // Novo Item
        document.getElementById('modalNovoItem').addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('form-novo-item').action    = btn.dataset.action;
            document.getElementById('ni-secao-nome').textContent = btn.dataset.secaoNome;
            document.getElementById('ni-ordem').value           = btn.dataset.proximoOrdem;
        });

        // Editar Item
        document.getElementById('modalEditarItem').addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('form-editar-item').action    = btn.dataset.action;
            document.getElementById('ei-nome').value              = btn.dataset.nome;
            document.getElementById('ei-descricao').value         = btn.dataset.descricao || '';
            document.getElementById('ei-nota_maxima').value       = btn.dataset.nota_maxima;
            document.getElementById('ei-peso').value              = btn.dataset.peso;
            document.getElementById('ei-ordem').value             = btn.dataset.ordem;
            document.getElementById('ei-indicador_capes').value   = btn.dataset.indicador_capes || '';
            document.getElementById('ei-obrigatorio').checked     = btn.dataset.obrigatorio === '1';
            document.getElementById('ei-ativo').checked           = btn.dataset.ativo === '1';
        });

        // Confirmar Exclusão genérico
        document.getElementById('modalConfirmarExclusao').addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('excluir-label').innerHTML   = btn.dataset.label;
            document.getElementById('form-excluir-generico').action = btn.dataset.action;
        });
    </script>
</x-app-layout>
