<x-app-layout>
    <div class="container-fluid">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $erro)
                        <li>{{ $erro }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Template LaTeX do programa</h1>
            <p class="text-muted mb-0">
                {{ $programa->nome }} — Envie o modelo oficial (.zip) do projeto LaTeX do programa. Com o template
                cadastrado, os discentes enviam o trabalho em LaTeX e o sistema compila, extrai os elementos vitais
                e confere com o cadastro. Sem template, o programa usa o envio de PDF, sem análise por IA.
            </p>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                @if ($template)
                    <dl class="row mb-3">
                        <dt class="col-sm-3">Arquivo</dt>
                        <dd class="col-sm-9">{{ $template->arquivo_nome_original }}</dd>
                        <dt class="col-sm-3">Arquivo .tex principal</dt>
                        <dd class="col-sm-9">{{ $template->tex_principal }}</dd>
                        <dt class="col-sm-3">Classe do documento</dt>
                        <dd class="col-sm-9">{{ $template->classe_documento ?? '—' }}</dd>
                        <dt class="col-sm-3">Enviado por</dt>
                        <dd class="col-sm-9">
                            {{ $template->enviadoPor?->name ?? '—' }}
                            em {{ $template->enviado_em?->format('d/m/Y H:i') }}
                        </dd>
                    </dl>
                    <a href="{{ route('programa-template.download', $programa) }}" class="btn btn-outline-secondary mb-3">
                        <i class="bi bi-download"></i> Baixar template
                    </a>
                @else
                    <p class="text-muted">Nenhum template cadastrado ainda.</p>
                @endif

                <form method="POST" action="{{ route('programa-template.store') }}" enctype="multipart/form-data">
                    @csrf
                    <label for="arquivo" class="form-label fw-semibold">
                        {{ $template ? 'Substituir template (.zip, até 50 MB)' : 'Enviar template (.zip, até 50 MB)' }}
                    </label>
                    <div class="input-group">
                        <input type="file" id="arquivo" name="arquivo" accept=".zip,application/zip" class="form-control" required>
                        <button type="submit" class="btn btn-primary">{{ $template ? 'Substituir' : 'Enviar' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
