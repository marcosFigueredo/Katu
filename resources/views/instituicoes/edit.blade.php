<x-app-layout>
    <div class="container-fluid">

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1" style="color: #00303A;">Instituição</h1>
                <p class="text-muted mb-0">
                    Dados institucionais utilizados nas telas, documentos e comunicações do sistema.
                </p>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

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

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="border rounded bg-white p-4 mb-4">
                            <img id="logo-preview" src="{{ $instituicao?->logoUrl() }}"
                                 alt="{{ $instituicao?->sigla ?? 'Logotipo' }}"
                                 class="img-fluid {{ $instituicao ? '' : 'd-none' }}"
                                 style="max-height: 160px; object-fit: contain;">
                            @unless ($instituicao)
                                <p id="logo-vazio" class="text-muted small mb-0">O logotipo aparecerá aqui.</p>
                            @endunless
                        </div>

                        @if ($instituicao)
                            <form method="POST" action="{{ route('instituicao.logo.update') }}" enctype="multipart/form-data" class="mb-4" data-no-dirty-check>
                                @csrf
                                <input id="logo-rapido" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="d-none"
                                       onchange="if (this.files[0]) this.form.submit()">
                                <button type="button" class="btn btn-outline-primary btn-sm"
                                        onclick="document.getElementById('logo-rapido').click()">
                                    <i class="bi bi-upload me-1"></i>Enviar logotipo
                                </button>
                                <div class="form-text">PNG, JPG ou WebP, até 2 MB.</div>
                            </form>
                        @endif

                        <h2 class="h5 fw-bold mb-1" style="color:#00303A;">
                            {{ $instituicao?->nome ?? 'Instituição não cadastrada' }}
                        </h2>
                        <p class="text-muted mb-3">
                            {{ $instituicao?->sigla ?? 'Sigla não informada' }}
                        </p>

                        <dl class="row small text-start mb-0">
                            <dt class="col-5 text-muted fw-normal">Departamento</dt>
                            <dd class="col-7 mb-2">{{ $instituicao?->departamento ?? '-' }}</dd>

                            <dt class="col-5 text-muted fw-normal">Cidade/UF</dt>
                            <dd class="col-7 mb-2">
                                {{ $instituicao?->cidade ?? '-' }}{{ $instituicao?->estado ? '/'.$instituicao->estado : '' }}
                            </dd>

                            <dt class="col-5 text-muted fw-normal">E-mail</dt>
                            <dd class="col-7 mb-2">{{ $instituicao?->email ?? '-' }}</dd>

                            <dt class="col-5 text-muted fw-normal">Telefone</dt>
                            <dd class="col-7 mb-0">{{ $instituicao?->telefone ?? '-' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h6 class="fw-bold mb-0">
                            <i class="bi bi-bank2 me-2" style="color:#00303A;"></i>Cadastro da Instituição
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data"
                              action="{{ $instituicao ? route('instituicao.update') : route('instituicoes.store') }}">
                            @csrf
                            @if ($instituicao)
                                @method('PUT')
                            @endif

                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="nome" class="form-label fw-semibold">Nome da instituição</label>
                                    <input id="nome" name="nome" type="text" class="form-control @error('nome') is-invalid @enderror"
                                           value="{{ old('nome', $instituicao->nome ?? '') }}" required>
                                    @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="sigla" class="form-label fw-semibold">Sigla</label>
                                    <input id="sigla" name="sigla" type="text" class="form-control @error('sigla') is-invalid @enderror"
                                           value="{{ old('sigla', $instituicao->sigla ?? '') }}" required>
                                    @error('sigla') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-8">
                                    <label for="departamento" class="form-label fw-semibold">Departamento</label>
                                    <input id="departamento" name="departamento" type="text" class="form-control @error('departamento') is-invalid @enderror"
                                           value="{{ old('departamento', $instituicao->departamento ?? '') }}">
                                    @error('departamento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="cnpj" class="form-label fw-semibold">CNPJ</label>
                                    <input id="cnpj" name="cnpj" type="text" class="form-control @error('cnpj') is-invalid @enderror"
                                           value="{{ old('cnpj', $instituicao->cnpj ?? '') }}">
                                    @error('cnpj') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12">
                                    <label for="endereco" class="form-label fw-semibold">Endereço</label>
                                    <input id="endereco" name="endereco" type="text" class="form-control @error('endereco') is-invalid @enderror"
                                           value="{{ old('endereco', $instituicao->endereco ?? '') }}">
                                    @error('endereco') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-5">
                                    <label for="cidade" class="form-label fw-semibold">Cidade</label>
                                    <input id="cidade" name="cidade" type="text" class="form-control @error('cidade') is-invalid @enderror"
                                           value="{{ old('cidade', $instituicao->cidade ?? '') }}">
                                    @error('cidade') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-2">
                                    <label for="estado" class="form-label fw-semibold">UF</label>
                                    <input id="estado" name="estado" type="text" maxlength="2" class="form-control @error('estado') is-invalid @enderror"
                                           value="{{ old('estado', $instituicao->estado ?? '') }}">
                                    @error('estado') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-5">
                                    <label for="cep" class="form-label fw-semibold">CEP</label>
                                    <input id="cep" name="cep" type="text" class="form-control @error('cep') is-invalid @enderror"
                                           value="{{ old('cep', $instituicao->cep ?? '') }}">
                                    @error('cep') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="telefone" class="form-label fw-semibold">Telefone</label>
                                    <input id="telefone" name="telefone" type="text" class="form-control @error('telefone') is-invalid @enderror"
                                           value="{{ old('telefone', $instituicao->telefone ?? '') }}">
                                    @error('telefone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="email" class="form-label fw-semibold">E-mail institucional</label>
                                    <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror"
                                           value="{{ old('email', $instituicao->email ?? '') }}">
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="site" class="form-label fw-semibold">Site</label>
                                    <input id="site" name="site" type="text" class="form-control @error('site') is-invalid @enderror"
                                           value="{{ old('site', $instituicao->site ?? '') }}">
                                    @error('site') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12">
                                    <label for="logo" class="form-label fw-semibold">
                                        Logotipo (PNG, JPG ou WebP, até 2 MB){{ $instituicao ? '' : ' — obrigatório' }}
                                    </label>
                                    <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"
                                           class="form-control @error('logo') is-invalid @enderror"
                                           @if (! $instituicao) required @endif
                                           onchange="if (this.files[0]) { const img = document.getElementById('logo-preview'); img.src = URL.createObjectURL(this.files[0]); img.classList.remove('d-none'); document.getElementById('logo-vazio')?.remove(); }">
                                    @if ($instituicao)
                                        <div class="form-text">Deixe em branco para manter o logotipo atual.</div>
                                    @endif
                                    @error('logo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary fw-semibold">
                                    <i class="bi bi-floppy-fill me-2"></i>Salvar instituição
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
