<x-app-layout>
    <div class="container-fluid" style="max-width: 900px;">
        <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Quadriênios de avaliação</h1>
        <p class="text-muted mb-3">
            O período de avaliação do programa é sempre o quadriênio. A Secretaria cadastra cada quadriênio;
            a CPA ajusta as faixas das classes de qualidade e os pesos.
        </p>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        @endif

        @foreach ($quadrienios as $quadrienio)
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="fw-semibold">{{ $quadrienio->nome }}</div>
                    <div class="small text-muted mb-2">{{ $quadrienio->inicio_em->format('d/m/Y') }} a {{ $quadrienio->fim_em->format('d/m/Y') }}</div>
                    <details>
                        <summary class="small">Editar</summary>
                        <form method="POST" action="{{ route('quadrienios.update', $quadrienio) }}" class="row g-2 mt-1">
                            @csrf
                            @method('PUT')
                            @include('quadrienios._campos', ['quadrienio' => $quadrienio, 'idBase' => 'q'.$quadrienio->id])
                            <div class="col-12"><button class="btn btn-sm btn-primary">Salvar</button></div>
                        </form>
                    </details>
                </div>
            </div>
        @endforeach

        <div class="card border-0 shadow-sm mb-5">
            <div class="card-body">
                <h2 class="h6 fw-bold">Novo quadriênio</h2>
                <form method="POST" action="{{ route('quadrienios.store') }}" class="row g-2">
                    @csrf
                    @include('quadrienios._campos', ['quadrienio' => null, 'idBase' => 'novo'])
                    <div class="col-12"><button class="btn btn-sm btn-primary">Cadastrar quadriênio</button></div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
