<x-app-layout>
    <div class="container-fluid" style="max-width: 900px;">
        <a href="{{ route('cpa.periodos.index') }}" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Períodos e pesos</a>
        <h1 class="h3 fw-bold mt-2 mb-1" style="color:#00303A;">Pesos {{ $grupo === 'fonte' ? 'das fontes' : 'das dimensões' }} — {{ $periodo->nome }}</h1>
        <p class="text-muted mb-3">
            Método SWARA: coloque os itens em ordem de importância (1 = mais importante) e diga, para cada um, quanto ele é
            <strong>menos importante que o anterior</strong> (0 = tão importante quanto; 0,10 = 10% menos; 0,50 = metade).
            O primeiro da ordem não precisa de valor. O sistema calcula os pesos, que somam 100%.
            {{ $grupo === 'fonte' ? 'Estes pesos combinam as fontes no índice do programa.' : 'Estes pesos combinam as 8 dimensões no índice de cada trabalho.' }}
        </p>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('cpa.pesos.update', [$periodo, $grupo]) }}">
            @csrf
            @method('PUT')
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>{{ $grupo === 'fonte' ? 'Fonte' : 'Dimensão' }}</th>
                            <th style="width:110px;">Ordem</th>
                            <th style="width:170px;">Menos importante que o anterior</th>
                            <th class="text-end">Peso atual</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rotulos as $chave => $rotulo)
                            <tr>
                                <td>{{ $rotulo }}</td>
                                <td>
                                    <input type="number" name="ordem[{{ $chave }}]" min="1" max="{{ count($rotulos) }}" required class="form-control form-control-sm"
                                           aria-label="Ordem de {{ $rotulo }}" value="{{ old("ordem.$chave", $definidos[$chave]->ordem ?? $loop->iteration) }}">
                                </td>
                                <td>
                                    <input type="number" name="s[{{ $chave }}]" step="0.01" min="0" max="1" required class="form-control form-control-sm"
                                           aria-label="Quanto {{ $rotulo }} é menos importante" value="{{ old("s.$chave", $definidos[$chave]->s_j ?? 0) }}">
                                </td>
                                <td class="text-end">{{ number_format(($pesos[$chave] ?? 0) * 100, 1, ',', '') }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($definidos->isEmpty())
                <p class="small text-muted">Ainda sem SWARA neste período: os pesos atuais são iguais.</p>
            @else
                <p class="small text-muted">Definidos por {{ $definidos->first()->definidoPor?->name ?? '—' }} em {{ $definidos->first()->updated_at?->format('d/m/Y H:i') }}.</p>
            @endif
            <button class="btn btn-primary">Calcular e gravar pesos</button>
        </form>
    </div>
</x-app-layout>
