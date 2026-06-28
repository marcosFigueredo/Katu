<x-app-layout>
    <div class="container-fluid">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('baremas.index') }}" class="text-decoration-none">Baremas</a>
                </li>
                <li class="breadcrumb-item active">Novo barema</li>
            </ol>
        </nav>

        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1" style="color: #00303A;">Novo Barema</h1>
            <p class="text-muted mb-0">
                Defina as informações básicas. Após salvar, você adicionará as seções e os itens de avaliação.
            </p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">

                        <form method="POST" action="{{ route('baremas.store') }}">
                            @csrf

                            <div class="mb-3">
                                <label for="nome" class="form-label fw-semibold">Nome do barema</label>
                                <input type="text"
                                       id="nome"
                                       name="nome"
                                       value="{{ old('nome') }}"
                                       placeholder="Ex.: Barema de Qualificação PPGMSB 2026"
                                       class="form-control @error('nome') is-invalid @enderror"
                                       required>
                                @error('nome')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label for="tipo" class="form-label fw-semibold">Tipo</label>
                                    <select id="tipo" name="tipo"
                                            class="form-select @error('tipo') is-invalid @enderror" required>
                                        <option value="" disabled {{ old('tipo') ? '' : 'selected' }}>Selecione…</option>
                                        <option value="qualificacao"              {{ old('tipo') === 'qualificacao'              ? 'selected' : '' }}>Qualificação</option>
                                        <option value="defesa"                    {{ old('tipo') === 'defesa'                    ? 'selected' : '' }}>Defesa</option>
                                        <option value="qualificacao_confidencial" {{ old('tipo') === 'qualificacao_confidencial' ? 'selected' : '' }}>Qualificação Confidencial</option>
                                        <option value="defesa_confidencial"       {{ old('tipo') === 'defesa_confidencial'       ? 'selected' : '' }}>Defesa Confidencial</option>
                                    </select>
                                    @error('tipo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-sm-6">
                                    <label for="nivel" class="form-label fw-semibold">Nível</label>
                                    <select id="nivel" name="nivel"
                                            class="form-select @error('nivel') is-invalid @enderror" required>
                                        <option value="ambos" {{ old('nivel', 'ambos') === 'ambos' ? 'selected' : '' }}>
                                            Mestrado e Doutorado
                                        </option>
                                        <option value="mestrado" {{ old('nivel') === 'mestrado' ? 'selected' : '' }}>
                                            Mestrado
                                        </option>
                                        <option value="doutorado" {{ old('nivel') === 'doutorado' ? 'selected' : '' }}>
                                            Doutorado
                                        </option>
                                    </select>
                                    @error('nivel')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="versao" class="form-label fw-semibold">Versão</label>
                                <input type="text"
                                       id="versao"
                                       name="versao"
                                       value="{{ old('versao', date('Y') . '.1') }}"
                                       placeholder="Ex.: 2026.1"
                                       class="form-control @error('versao') is-invalid @enderror"
                                       style="max-width: 160px;"
                                       required>
                                @error('versao')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="descricao" class="form-label fw-semibold">
                                    Descrição <span class="text-muted fw-normal">(opcional)</span>
                                </label>
                                <textarea id="descricao"
                                          name="descricao"
                                          rows="3"
                                          class="form-control @error('descricao') is-invalid @enderror"
                                          placeholder="Observações gerais sobre este barema…">{{ old('descricao') }}</textarea>
                                @error('descricao')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex gap-2 pt-2 border-top">
                                <button type="submit" class="btn btn-primary fw-semibold">
                                    <i class="bi bi-floppy-fill me-2"></i>Criar barema
                                </button>
                                <a href="{{ route('baremas.index') }}" class="btn btn-outline-secondary">
                                    Cancelar
                                </a>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
