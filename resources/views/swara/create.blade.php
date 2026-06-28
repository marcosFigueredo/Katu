<x-app-layout>
    <div class="container-fluid">

        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('baremas.index') }}">Baremas</a></li>
                <li class="breadcrumb-item"><a href="{{ route('swara.index', $barema) }}">SWARA — {{ $barema->nome }}</a></li>
                <li class="breadcrumb-item active">Nova sessão</li>
            </ol>
        </nav>

        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0" style="color:#00303A;">
                            <i class="bi bi-sliders me-2"></i>Nova Sessão SWARA
                        </h5>
                        <p class="text-muted mb-0 small mt-1">
                            Ordene os critérios do mais importante para o menos importante e informe a importância relativa de cada um em relação ao anterior.
                        </p>
                    </div>
                    <div class="card-body">

                        <form method="POST" action="{{ route('swara.store', $barema) }}">
                            @csrf

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Nome da sessão <span class="text-danger">*</span></label>
                                    <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror"
                                           value="{{ old('nome') }}" required placeholder="Ex: Calibração 2026-S1">
                                    @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Aprovado por</label>
                                    <input type="text" name="aprovado_por" class="form-control"
                                           value="{{ old('aprovado_por') }}" placeholder="Nome do responsável">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="2"
                                          placeholder="Contexto ou justificativa desta calibração">{{ old('descricao') }}</textarea>
                            </div>

                            <hr>
                            <h6 class="fw-bold mb-3" style="color:#00303A;">
                                <i class="bi bi-sort-numeric-down me-2"></i>Ordenação e julgamentos
                            </h6>
                            <p class="text-muted small mb-3">
                                O primeiro critério sempre recebe peso máximo (s₁ = 0). Para os demais, informe s_j ≥ 0 (quanto maior, menos importante que o anterior).
                            </p>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:60px">Ordem</th>
                                            <th>Critério</th>
                                            <th style="width:180px">
                                                s_j (importância relativa)
                                                <i class="bi bi-info-circle text-muted ms-1"
                                                   title="0 = igual ao anterior · 0.5 = 50% menos importante · 1 = metade do peso"
                                                   data-bs-toggle="tooltip"></i>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($itens as $i => $item)
                                            <input type="hidden" name="ordem[]" value="{{ $item->id }}">
                                            <tr>
                                                <td class="text-center fw-bold text-muted">{{ $i + 1 }}</td>
                                                <td>
                                                    <span class="fw-semibold">{{ $item->nome }}</span>
                                                    @if ($item->descricao)
                                                        <div class="text-muted small">{{ $item->descricao }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($i === 0)
                                                        <input type="text" class="form-control form-control-sm text-center bg-light"
                                                               value="0 (fixo)" disabled>
                                                        <input type="hidden" name="s_j[{{ $item->id }}]" value="0">
                                                    @else
                                                        <input type="number" name="s_j[{{ $item->id }}]"
                                                               class="form-control form-control-sm @error('s_j.'.$item->id) is-invalid @enderror"
                                                               step="0.01" min="0" max="5"
                                                               value="{{ old('s_j.'.$item->id, 0) }}"
                                                               placeholder="0.00">
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-3">
                                <a href="{{ route('swara.index', $barema) }}" class="btn btn-outline-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary fw-semibold">
                                    <i class="bi bi-calculator me-1"></i> Calcular e salvar
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
