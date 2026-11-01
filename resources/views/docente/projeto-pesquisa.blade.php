@use('App\Models\ProjetoPesquisa')
<x-app-layout>
    <div class="container-fluid" style="max-width: 900px;">
        <a href="{{ route('avaliacoes.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Voltar à Área do Docente
        </a>
        <h1 class="h3 fw-bold mt-2 mb-1" style="color:#00303A;">Meu projeto de pesquisa</h1>
        <p class="text-muted mb-3">
            Seus orientandos são vinculados automaticamente a este projeto e indicam qual objetivo dele a pesquisa avança.
            Sem projeto cadastrado, eles não conseguem enviar a qualificação nem a defesa à homologação.
        </p>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($ativo)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h2 class="h5 fw-bold mb-0">Projeto ativo</h2>
                        <span class="badge text-bg-light">{{ $ativo->orientandos_count }} orientando(s) vinculado(s)</span>
                    </div>
                    <form method="POST" action="{{ route('meu-projeto.update', $ativo) }}">
                        @csrf
                        @method('PUT')
                        @include('docente.partials.projeto-campos', ['projeto' => $ativo, 'prefixoId' => 'ativo'])
                        <button class="btn btn-outline-primary btn-sm">Salvar correções</button>
                    </form>
                </div>
            </div>
        @else
            <div class="alert alert-warning">Você ainda não cadastrou seu projeto de pesquisa.</div>
        @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-2">{{ $ativo ? 'Passar a um novo projeto' : 'Cadastrar projeto' }}</h2>
                @if ($ativo)
                    <p class="small text-muted">O projeto atual será concluído e seus orientandos passarão ao novo projeto, indicando se mantêm seus objetivos.</p>
                @endif
                <form method="POST" action="{{ route('meu-projeto.store') }}">
                    @csrf
                    @include('docente.partials.projeto-campos', ['projeto' => null, 'prefixoId' => 'novo'])
                    <button class="btn btn-primary btn-sm">Cadastrar projeto</button>
                </form>
            </div>
        </div>

        @if ($concluidos->isNotEmpty())
            <h2 class="h6 fw-bold">Projetos anteriores</h2>
            <ul class="list-group mb-5">
                @foreach ($concluidos as $projeto)
                    <li class="list-group-item">
                        <div class="fw-semibold">{{ $projeto->titulo }}</div>
                        <div class="small text-muted">
                            {{ ProjetoPesquisa::SITUACOES[$projeto->situacao] }} ·
                            {{ $projeto->inicio_em?->format('d/m/Y') }} a {{ $projeto->fim_em?->format('d/m/Y') ?? '—' }}
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-app-layout>
