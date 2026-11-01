<x-app-layout>
    <div class="container-fluid" style="max-width: 1100px;">
        <h1 class="h3 fw-bold mb-1" style="color:#00303A;">CPA — Períodos e pesos</h1>
        <p class="text-muted mb-3">
            Cada período de avaliação tem as faixas das 5 classes de qualidade e os pesos definidos pela CPA com o SWARA:
            os das <strong>dimensões</strong> (índice de cada trabalho) e os das <strong>fontes</strong> (índice do programa).
            Sem pesos definidos, todos valem igual.
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
                        <summary class="small">Editar período e faixas</summary>
                        <form method="POST" action="{{ route('cpa.periodos.update', $periodo) }}" class="mt-2">
                            @csrf
                            @method('PUT')
                            @include('cpa._periodo-campos', ['periodo' => $periodo, 'idBase' => 'p'.$periodo->id])
                            <button class="btn btn-sm btn-primary mt-2">Salvar</button>
                        </form>
                    </details>
                </div>
            </div>
        @endforeach

        <div class="card border-0 shadow-sm mb-5">
            <div class="card-body">
                <h2 class="h6 fw-bold">Novo período</h2>
                <form method="POST" action="{{ route('cpa.periodos.store') }}">
                    @csrf
                    @include('cpa._periodo-campos', ['periodo' => null, 'idBase' => 'novo'])
                    <button class="btn btn-sm btn-primary mt-2">Criar período</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
