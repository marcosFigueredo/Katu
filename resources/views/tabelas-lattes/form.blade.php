<x-app-layout>
    <div class="container-fluid" style="max-width: 720px;">
        <a href="{{ route('tabelas-lattes.index', ['lista' => $lista]) }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
        <h1 class="h3 fw-bold mt-2 mb-3" style="color:#00303A;">
            {{ $item->exists ? 'Editar' : 'Incluir' }} {{ $lista === 'cnpq' ? 'área do CNPq' : 'setor CNAE' }}
        </h1>

        <form method="POST"
              action="{{ $item->exists ? route('tabelas-lattes.update', ['lista' => $lista, 'id' => $item->id]) : route('tabelas-lattes.store', ['lista' => $lista]) }}"
              class="card border-0 shadow-sm card-body">
            @csrf
            @if ($item->exists) @method('PUT') @endif

            <div class="mb-3">
                <label for="codigo" class="form-label fw-semibold">Código</label>
                <input id="codigo" name="codigo" value="{{ old('codigo', $item->codigo) }}" class="form-control @error('codigo') is-invalid @enderror" required>
                @error('codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="nome" class="form-label fw-semibold">Nome</label>
                <input id="nome" name="nome" value="{{ old('nome', $item->nome) }}" class="form-control @error('nome') is-invalid @enderror" required>
                @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @if ($lista === 'cnpq')
                <div class="mb-3">
                    <label for="nivel" class="form-label fw-semibold">Nível</label>
                    <select id="nivel" name="nivel" class="form-select" required>
                        @foreach (\App\Models\AreaCnpq::NIVEIS as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected(old('nivel', $item->nivel) === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label for="pai_id" class="form-label fw-semibold">Pertence a</label>
                    <select id="pai_id" name="pai_id" class="form-select @error('pai_id') is-invalid @enderror">
                        <option value="">— (grande área)</option>
                        @foreach ($pais as $pai)
                            <option value="{{ $pai->id }}" @selected((int) old('pai_id', $item->pai_id) === $pai->id)>{{ $pai->codigo }} — {{ $pai->nome }}</option>
                        @endforeach
                    </select>
                    @error('pai_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @else
                <div class="mb-3">
                    <label for="secao" class="form-label fw-semibold">Seção (letra)</label>
                    <input id="secao" name="secao" maxlength="1" value="{{ old('secao', $item->secao) }}" class="form-control">
                </div>
            @endif

            <div><button class="btn btn-primary">Salvar</button></div>
        </form>
    </div>
</x-app-layout>
