<x-app-layout>
    <div class="container-fluid">
        <h1 class="h3 fw-bold mb-1" style="color:#00303A;">{{ $criterio->exists ? 'Editar pergunta da CPA' : 'Nova pergunta da CPA' }}</h1>
        <p class="text-muted mb-4">{{ $programa?->nome ?? 'Nenhum programa ativo' }}</p>
        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="card border-0 shadow-sm" style="max-width:920px;">
            <div class="card-body">
                <form method="POST" action="{{ $criterio->exists ? route('criterios-cpa.update', $criterio) : route('criterios-cpa.store') }}">
                    @csrf @if($criterio->exists) @method('PUT') @endif
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="pergunta">Pergunta (resposta sim ou não)</label>
                            <textarea class="form-control" id="pergunta" name="pergunta" rows="3" maxlength="1000" required>{{ old('pergunta', $criterio->pergunta) }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="etapa">Etapa</label>
                            <select class="form-select" id="etapa" name="etapa">
                                <option value="">Qualificação e defesa</option>
                                <option value="qualificacao" @selected(old('etapa', $criterio->etapa) === 'qualificacao')>Qualificação</option>
                                <option value="defesa" @selected(old('etapa', $criterio->etapa) === 'defesa')>Defesa</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="nivel">Nível</label>
                            <select class="form-select" id="nivel" name="nivel">
                                <option value="">Mestrado e doutorado</option>
                                <option value="mestrado" @selected(old('nivel', $criterio->nivel) === 'mestrado')>Mestrado</option>
                                <option value="doutorado" @selected(old('nivel', $criterio->nivel) === 'doutorado')>Doutorado</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ordem">Ordem</label>
                            <input type="number" min="1" class="form-control" id="ordem" name="ordem" value="{{ old('ordem', $criterio->ordem ?? 1) }}" required>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('criterios-cpa.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        <button class="btn btn-primary">Salvar pergunta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
