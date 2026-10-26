<x-app-layout>
<div class="container-fluid">

    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('perguntas-estrategicas.index') }}">Perguntas Estratégicas</a></li>
            <li class="breadcrumb-item active">Nova pergunta</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0" style="color:#00303A;">
                        <i class="bi bi-question-circle me-2"></i>Nova Pergunta Estratégica
                    </h5>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ route('perguntas-estrategicas.store') }}" x-data="perguntaForm()">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Item CAPES (opcional)</label>
                            <input type="text" name="item_capes" class="form-control @error('item_capes') is-invalid @enderror"
                                   value="{{ old('item_capes') }}" placeholder="Ex: 2.1" style="max-width:200px;">
                            @error('item_capes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Pergunta <span class="text-danger">*</span></label>
                            <textarea name="texto" rows="2" class="form-control @error('texto') is-invalid @enderror" required
                                      placeholder="Ex: Os trabalhos de conclusão possuem aderência às áreas de concentração e linhas de pesquisa?">{{ old('texto') }}</textarea>
                            @error('texto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-semibold mb-0">Indicadores vinculados <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-sm btn-outline-primary" @click="adicionar()">
                                <i class="bi bi-plus-lg me-1"></i> Adicionar indicador
                            </button>
                        </div>
                        <p class="text-muted small">Se mais de um indicador for vinculado, a pergunta assume o pior status entre eles (ex.: se um indicador está "vermelho", a pergunta inteira aparece como "não atende").</p>

                        <template x-for="(linha, i) in linhas" :key="i">
                            <div class="row g-2 align-items-end mb-2 p-2 border rounded">
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Código</label>
                                    <input type="text" :name="'indicadores['+i+'][codigo]'" x-model="linha.codigo"
                                           class="form-control form-control-sm" placeholder="Ex: A17" list="codigos-disponiveis" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Direção</label>
                                    <select :name="'indicadores['+i+'][direcao]'" x-model="linha.direcao" class="form-select form-select-sm" required>
                                        <option value="maior_melhor">Maior é melhor</option>
                                        <option value="menor_melhor">Menor é melhor</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Limiar verde</label>
                                    <input type="number" step="0.01" :name="'indicadores['+i+'][limiar_verde]'" x-model="linha.limiar_verde" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Limiar amarelo</label>
                                    <input type="number" step="0.01" :name="'indicadores['+i+'][limiar_amarelo]'" x-model="linha.limiar_amarelo" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger" @click="remover(i)" x-show="linhas.length > 1">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <datalist id="codigos-disponiveis">
                            @foreach ($codigosDisponiveis as $cod)
                                <option value="{{ $cod->codigo }}">{{ $cod->nome }}</option>
                            @endforeach
                        </datalist>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('perguntas-estrategicas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary fw-semibold">
                                <i class="bi bi-plus-lg me-1"></i> Criar pergunta
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

</div>

<script>
function perguntaForm() {
    return {
        linhas: [{ codigo: '', direcao: 'maior_melhor', limiar_verde: '', limiar_amarelo: '' }],
        adicionar() {
            this.linhas.push({ codigo: '', direcao: 'maior_melhor', limiar_verde: '', limiar_amarelo: '' });
        },
        remover(i) {
            this.linhas.splice(i, 1);
        },
    };
}
</script>
</x-app-layout>
