@use('App\Models\ProjetoPesquisa')
<x-app-layout>
    <div class="container-fluid" style="max-width: 900px;">
        <a href="{{ route('meu-trabalho.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Voltar a Meu trabalho
        </a>
        <h1 class="h3 fw-bold mt-2 mb-1" style="color:#00303A;">Projeto de pesquisa</h1>
        <p class="text-muted mb-3">
            Sua dissertação está vinculada ao projeto de pesquisa do seu orientador. Indique qual objetivo do projeto a sua pesquisa avança.
            Exigido para enviar a qualificação e a defesa à homologação.
        </p>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        @endif

        @if (! $vigente)
            <div class="alert alert-warning">Seu orientador ainda não tem projeto de pesquisa ativo cadastrado.</div>
        @else
            @php $projeto = $vigente->projeto; @endphp
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="small text-muted mb-1">
                        Projeto de {{ $projeto->docenteProgramaVinculo?->docenteProfile?->user?->name }} ·
                        {{ ProjetoPesquisa::SITUACOES[$projeto->situacao] }} · desde {{ $projeto->inicio_em?->format('d/m/Y') }}
                    </div>
                    <h2 class="h5 fw-bold">{{ $projeto->titulo }}</h2>
                    <p class="mb-0" style="white-space: pre-line;">{{ $projeto->descricao }}</p>
                </div>
            </div>

            @if (! $editavel)
                <div class="alert alert-info"><i class="bi bi-lock-fill me-2"></i>{{ \App\Http\Controllers\TrabalhoProjetoController::MENSAGEM_JANELA }}</div>
            @endif

            <form method="POST" action="{{ route('meu-trabalho.projeto.update') }}" class="mb-5"
                  x-data="{ mantem: '{{ old('mantem_objetivo', $anterior !== null ? '' : '0') }}' }">
                @csrf
                @method('PUT')
                <fieldset @disabled(! $editavel)>
                    @if ($anterior !== null)
                        <div class="alert alert-light border">
                            <div class="small fw-semibold mb-1">O projeto do seu orientador mudou. Antes, você indicou:</div>
                            <p class="mb-2" style="white-space: pre-line;">{{ $anterior }}</p>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mantem_objetivo" id="mantem_1" value="1" x-model="mantem">
                                <label class="form-check-label" for="mantem_1">Mantenho estes objetivos</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mantem_objetivo" id="mantem_0" value="0" x-model="mantem">
                                <label class="form-check-label" for="mantem_0">Não mantenho; indico novos objetivos</label>
                            </div>
                        </div>
                    @endif

                    <div class="mb-3" @if ($anterior !== null) x-show="mantem === '0'" @endif>
                        <label for="objetivo" class="form-label fw-semibold">Qual objetivo do projeto sua pesquisa avança?</label>
                        <textarea id="objetivo" name="objetivo" rows="5" maxlength="3000" class="form-control">{{ old('objetivo', $vigente->objetivo) }}</textarea>
                        @if ($vigente->objetivo_informado_em)
                            <div class="form-text">Informado em {{ $vigente->objetivo_informado_em->format('d/m/Y H:i') }}.</div>
                        @endif
                    </div>
                    <button class="btn btn-primary">Salvar objetivo</button>
                </fieldset>
            </form>
        @endif
    </div>
</x-app-layout>
