<x-app-layout>
    <div class="container-fluid" style="max-width: 900px;">
        <a href="{{ route('avaliacoes.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Voltar às bancas
        </a>
        <h1 class="h3 fw-bold mt-2 mb-1" style="color:#00303A;">Dados para o Lattes</h1>
        <p class="text-muted mb-3">
            Participação em bancas de trabalhos de conclusão — confira e copie cada campo para o seu Currículo Lattes.
            Campos com "—" não foram informados.
        </p>

        <div class="card border-0 shadow-sm">
            <ul class="list-group list-group-flush">
                @foreach ($campos as $campo)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="flex-grow-1">
                                <div class="small text-muted">{{ $campo['rotulo'] }}</div>
                                <div class="fw-semibold" style="white-space: pre-line;" data-valor>{{ $campo['valor'] }}</div>
                            </div>
                            @if ($campo['valor'] !== '—')
                                <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0" data-copiar>
                                    <i class="bi bi-clipboard me-1"></i>Copiar
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-copiar]').forEach(function (botao) {
            botao.addEventListener('click', function () {
                const valor = botao.closest('li').querySelector('[data-valor]').innerText;
                navigator.clipboard.writeText(valor).then(function () {
                    botao.innerHTML = '<i class="bi bi-check2 me-1"></i>Copiado';
                    setTimeout(function () { botao.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copiar'; }, 1500);
                });
            });
        });
    </script>
</x-app-layout>
