<x-app-layout>
    <div class="container-fluid">

        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('mabac.index') }}">MABAC</a></li>
                <li class="breadcrumb-item active">Nova análise</li>
            </ol>
        </nav>

        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0" style="color:#00303A;">
                            <i class="bi bi-bar-chart-steps me-2"></i>Nova Análise MABAC
                        </h5>
                        <p class="text-muted small mb-0 mt-1">
                            Selecione o barema e o período para comparar os trabalhos finalizados.
                        </p>
                    </div>
                    <div class="card-body">

                        <form method="POST" action="{{ route('mabac.store') }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nome da análise <span class="text-danger">*</span></label>
                                <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror"
                                       value="{{ old('nome') }}" required placeholder="Ex: MABAC Defesas 2025">
                                @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Barema <span class="text-danger">*</span></label>
                                <select name="barema_id" class="form-select @error('barema_id') is-invalid @enderror" required>
                                    <option value="">Selecione…</option>
                                    @foreach ($baremas as $barema)
                                        <option value="{{ $barema->id }}" {{ old('barema_id') == $barema->id ? 'selected' : '' }}>
                                            {{ $barema->nome }} ({{ $barema->tipo }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('barema_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Tipo de banca <span class="text-danger">*</span></label>
                                    <select name="tipo_banca" class="form-select @error('tipo_banca') is-invalid @enderror" required>
                                        <option value="ambos"        {{ old('tipo_banca', 'ambos') === 'ambos'        ? 'selected' : '' }}>Ambos</option>
                                        <option value="qualificacao" {{ old('tipo_banca') === 'qualificacao' ? 'selected' : '' }}>Qualificação</option>
                                        <option value="defesa"       {{ old('tipo_banca') === 'defesa'       ? 'selected' : '' }}>Defesa</option>
                                    </select>
                                    @error('tipo_banca')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Linha de pesquisa</label>
                                    <select name="linha_pesquisa_id" class="form-select">
                                        <option value="">Todas as linhas</option>
                                        @foreach ($linhas as $linha)
                                            <option value="{{ $linha->id }}" {{ old('linha_pesquisa_id') == $linha->id ? 'selected' : '' }}>
                                                {{ $linha->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Período — início <span class="text-danger">*</span></label>
                                    <input type="date" name="periodo_inicio" class="form-control @error('periodo_inicio') is-invalid @enderror"
                                           value="{{ old('periodo_inicio') }}" required>
                                    @error('periodo_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Período — fim <span class="text-danger">*</span></label>
                                    <input type="date" name="periodo_fim" class="form-control @error('periodo_fim') is-invalid @enderror"
                                           value="{{ old('periodo_fim') }}" required>
                                    @error('periodo_fim')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('mabac.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary fw-semibold">
                                    <i class="bi bi-play-circle me-1"></i> Gerar análise
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
