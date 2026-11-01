<x-app-layout>
    <div class="container-fluid" style="max-width: 1100px;">
        <h1 class="h3 fw-bold mb-1" style="color:#00303A;">CPA — Faixas e pesos do quadriênio</h1>
        <p class="text-muted mb-3">
            Os quadriênios são cadastrados pela Secretaria. Em cada um, a CPA ajusta as faixas das 5 classes de qualidade
            e define com o SWARA os pesos das <strong>dimensões</strong> (índice de cada trabalho) e das <strong>fontes</strong>
            (índice do programa). Sem pesos definidos, todos valem igual.
        </p>
        @include('cpa._nav')

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        @endif

        @foreach ($periodos as $periodo)
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                        <div>
                            <h2 class="h5 fw-bold mb-0">{{ $periodo->nome }}</h2>
                            <div class="small text-muted">{{ $periodo->inicio_em->format('d/m/Y') }} a {{ $periodo->fim_em->format('d/m/Y') }}</div>
                        </div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('cpa.pesos.edit', [$periodo, 'dimensao']) }}">
                                Pesos das dimensões
                                @if ($periodo->pesosQualidade->where('grupo', 'dimensao')->isNotEmpty())<i class="bi bi-check-lg"></i>@endif
                            </a>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('cpa.pesos.edit', [$periodo, 'fonte']) }}">
                                Pesos das fontes
                                @if ($periodo->pesosQualidade->where('grupo', 'fonte')->isNotEmpty())<i class="bi bi-check-lg"></i>@endif
                            </a>
                        </div>
                    </div>
                    <details>
                        <summary class="small">Ajustar faixas</summary>
                        <form method="POST" action="{{ route('cpa.periodos.update', $periodo) }}" class="mt-2">
                            @csrf
                            @method('PUT')
                            @include('cpa._periodo-campos', ['periodo' => $periodo, 'idBase' => 'p'.$periodo->id, 'soFaixas' => true])
                            <button class="btn btn-sm btn-primary mt-2">Salvar</button>
                        </form>
                    </details>
                </div>
            </div>
        @endforeach

        @if ($periodos->isEmpty())
            <div class="alert alert-info">Nenhum quadriênio cadastrado. A Secretaria cadastra em Configurações → Quadriênios.</div>
        @endif
    </div>
</x-app-layout>
