<x-app-layout>
    @php
        $tipoLabel = match ($submissao->tipo_documento) {
            'qualificacao' => 'Exame de Qualificação',
            'dissertacao'  => 'Dissertação',
            'tese'         => 'Tese',
            default        => $submissao->tipo_documento,
        };
    @endphp

    <div class="container-fluid">
        <div class="d-flex align-items-center gap-3 mb-4">
            <a href="{{ route('meu-trabalho.index') }}" class="btn btn-sm btn-outline-secondary">← Voltar</a>
            <div>
                <h1 class="h3 mb-0 fw-bold text-dark">Submissão LaTeX</h1>
                <p class="text-muted small mb-0">
                    {{ $tipoLabel }} · enviado em {{ $submissao->created_at?->format('d/m/Y \à\s H:i') }}
                    @if ($submissao->tex_principal)
                        · {{ $submissao->tex_principal }}
                    @endif
                </p>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div id="submissao-resultado"
             data-status="{{ $submissao->status }}"
             data-em-andamento="{{ ($emAndamento ?? ($submissao->status === 'processando')) ? '1' : '0' }}"
             data-url="{{ route('meu-trabalho.submissao-latex.show', $submissao) }}">
            @include('meu-trabalho.partials.submissao-latex-resultado')
        </div>
    </div>

    <script>
        (function () {
            const alvo = document.getElementById('submissao-resultado');
            if (!alvo || alvo.dataset.emAndamento !== '1') return;

            const consultar = function () {
                fetch(alvo.dataset.url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (dados) {
                        if (dados && dados.html) {
                            alvo.innerHTML = dados.html;
                            alvo.dataset.status = dados.status;
                            alvo.dataset.emAndamento = dados.em_andamento ? '1' : '0';
                        }
                        if (dados && dados.em_andamento) {
                            setTimeout(consultar, 5000);
                        }
                    })
                    .catch(function () { setTimeout(consultar, 5000); });
            };

            setTimeout(consultar, 5000);
        })();
    </script>
</x-app-layout>
