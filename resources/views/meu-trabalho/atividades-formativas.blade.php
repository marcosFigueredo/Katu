@use('App\Models\AtividadeFormativa')
@use('App\Models\AtividadeRealizada')
<x-app-layout>
    <div class="container-fluid" style="max-width: 900px;">
        <a href="{{ route('meu-trabalho.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Voltar a Meu trabalho
        </a>
        <h1 class="h3 fw-bold mt-2 mb-1" style="color:#00303A;">Atividades formativas</h1>
        <p class="text-muted mb-3">
            Lance cada atividade que você realizou. Sem as exigidas, o pedido não pode ser enviado à homologação;
            a Secretaria confere os lançamentos na homologação.
        </p>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($travado)
            <div class="alert alert-info"><i class="bi bi-lock-fill me-2"></i>{{ \App\Http\Controllers\AtividadesRealizadasController::MENSAGEM_TRAVADO }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        @endif

        @forelse ($atividades as $atividade)
            @php
                $meus = $lancamentos->get($atividade->id, collect());
                $validos = $meus->where('conferencia', '!=', 'nao_confirmada')->count();
            @endphp
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <h2 class="h6 fw-bold mb-1" style="color:#00303A;">{{ $atividade->nome }}</h2>
                            <div class="small text-muted">
                                Exigida no pedido de {{ mb_strtolower(AtividadeFormativa::ETAPAS[$atividade->etapa]) }}
                                @if ($atividade->detalhes) · {{ $atividade->detalhes }} @endif
                            </div>
                        </div>
                        @if ($atividade->informada_por === 'secretaria')
                            <span class="badge bg-secondary-subtle text-secondary-emphasis align-self-start">Informada pela Secretaria</span>
                        @else
                            <span @class(['badge align-self-start', 'bg-success-subtle text-success-emphasis' => $validos >= $atividade->quantidade_exigida, 'bg-warning-subtle text-warning-emphasis' => $validos < $atividade->quantidade_exigida])>
                                {{ $validos }} de {{ $atividade->quantidade_exigida }}
                            </span>
                        @endif
                    </div>

                    @if ($meus->isNotEmpty())
                        <ul class="list-group list-group-flush mt-2">
                            @foreach ($meus as $lancamento)
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div>{{ $lancamento->descricao }}</div>
                                        @if ($lancamento->link)
                                            <a href="{{ $lancamento->link }}" target="_blank" rel="noopener" class="small">{{ $lancamento->link }}</a>
                                        @endif
                                        <div class="small">
                                            @if ($lancamento->conferencia)
                                                <span @class(['badge', 'bg-success' => $lancamento->conferencia === 'confirmada', 'bg-danger' => $lancamento->conferencia === 'nao_confirmada'])>
                                                    {{ AtividadeRealizada::CONFERENCIA[$lancamento->conferencia] }}
                                                </span>
                                            @else
                                                <span class="text-muted">Aguardando conferência da Secretaria</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if (! $travado && $lancamento->conferencia !== 'confirmada' && $atividade->informada_por === 'estudante')
                                        <form method="POST" action="{{ route('meu-trabalho.atividades.destroy', $lancamento) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if (! $travado && $atividade->informada_por === 'estudante')
                        <form method="POST" action="{{ route('meu-trabalho.atividades.store') }}" class="row g-2 mt-2">
                            @csrf
                            <input type="hidden" name="atividade_formativa_id" value="{{ $atividade->id }}">
                            <div class="col-md-6">
                                <input type="text" name="descricao" class="form-control form-control-sm" maxlength="1000" required placeholder="O que você fez (ex.: oficina na escola X, 2025)">
                            </div>
                            <div class="col-md-4">
                                <input type="url" name="link" class="form-control form-control-sm" maxlength="500" placeholder="Link do comprovante (opcional)">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button class="btn btn-sm btn-outline-primary">Lançar</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="alert alert-light">O programa ainda não definiu atividades formativas.</div>
        @endforelse
    </div>
</x-app-layout>
