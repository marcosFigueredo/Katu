<x-app-layout>
    <div class="container-fluid">

        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('autoavaliacao.index') }}">Autoavaliação</a></li>
                <li class="breadcrumb-item active">Novo período</li>
            </ol>
        </nav>

        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0" style="color:#00303A;">
                            <i class="bi bi-clipboard2-data me-2"></i>Novo Período de Autoavaliação
                        </h5>
                    </div>
                    <div class="card-body">

                        <form method="POST" action="{{ route('autoavaliacao.store') }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
                                <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror"
                                       value="{{ old('nome') }}" required placeholder="Ex: Autoavaliação 2025/S2">
                                @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Ano <span class="text-danger">*</span></label>
                                    <input type="number" name="ano" class="form-control @error('ano') is-invalid @enderror"
                                           value="{{ old('ano', date('Y')) }}" min="2000" max="2099" required>
                                    @error('ano')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Semestre</label>
                                    <select name="semestre" class="form-select">
                                        <option value="">Anual (sem semestre)</option>
                                        <option value="1" {{ old('semestre') == '1' ? 'selected' : '' }}>1º semestre</option>
                                        <option value="2" {{ old('semestre') == '2' ? 'selected' : '' }}>2º semestre</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Data início <span class="text-danger">*</span></label>
                                    <input type="date" name="data_inicio" class="form-control @error('data_inicio') is-invalid @enderror"
                                           value="{{ old('data_inicio') }}" required>
                                    @error('data_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Data fim <span class="text-danger">*</span></label>
                                    <input type="date" name="data_fim" class="form-control @error('data_fim') is-invalid @enderror"
                                           value="{{ old('data_fim') }}" required>
                                    @error('data_fim')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('autoavaliacao.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary fw-semibold">
                                    <i class="bi bi-plus-lg me-1"></i> Criar período
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
