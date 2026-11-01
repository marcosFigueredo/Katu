@use('App\Models\RecomendacaoResposta')
<x-app-layout>
    <div class="container-fluid" style="max-width: 900px;">
        <a href="{{ route('meu-trabalho.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Voltar a Meu trabalho
        </a>
        <h1 class="h3 fw-bold mt-2 mb-1" style="color:#00303A;">Recomendações da qualificação</h1>
        <p class="text-muted mb-3">
            Responda cada recomendação da banca de qualificação: atendida, atendida em parte ou não atendida.
            Indique onde está no texto e justifique o que não foi atendido. Exigido para enviar a defesa à homologação; a banca da defesa vê as respostas.
        </p>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($travado)
            <div class="alert alert-info"><i class="bi bi-lock-fill me-2"></i>{{ \App\Http\Controllers\TrabalhoImpactoController::MENSAGEM_TRAVADO }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($recomendacoes->isEmpty())
            <div class="alert alert-light">A banca de qualificação não registrou recomendações.</div>
        @else
            <form method="POST" action="{{ route('meu-trabalho.recomendacoes.update') }}" class="mb-5">
                @csrf
                @method('PUT')
                <fieldset @disabled($travado)>
                    @foreach ($recomendacoes as $i => $r)
                        @php
                            $situacao = old("respostas.{$r->id}.situacao", $r->resposta?->situacao);
                        @endphp
                        <div class="card border-0 shadow-sm mb-3" x-data="{ situacao: '{{ $situacao }}' }">
                            <div class="card-body">
                                <p class="fw-semibold mb-2">{{ $i + 1 }}. {{ $r->texto }}</p>
                                <div class="d-flex flex-wrap gap-3 mb-2">
                                    @foreach (RecomendacaoResposta::SITUACOES as $valor => $rotulo)
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="respostas[{{ $r->id }}][situacao]" id="sit-{{ $r->id }}-{{ $valor }}"
                                                   value="{{ $valor }}" x-model="situacao" @checked($situacao === $valor) required>
                                            <label class="form-check-label" for="sit-{{ $r->id }}-{{ $valor }}">{{ $rotulo }}</label>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mb-2" x-show="situacao === 'atendida' || situacao === 'parcial'" x-cloak>
                                    <label class="form-label small" for="onde-{{ $r->id }}">Onde está no texto (capítulo, seção, página)</label>
                                    <input type="text" name="respostas[{{ $r->id }}][onde]" id="onde-{{ $r->id }}" class="form-control form-control-sm" maxlength="500"
                                           value="{{ old("respostas.{$r->id}.onde", $r->resposta?->onde) }}">
                                </div>
                                <div x-show="situacao === 'parcial' || situacao === 'nao_atendida'" x-cloak>
                                    <label class="form-label small" for="just-{{ $r->id }}">Justificativa</label>
                                    <textarea name="respostas[{{ $r->id }}][justificativa]" id="just-{{ $r->id }}" class="form-control form-control-sm" rows="2" maxlength="2000">{{ old("respostas.{$r->id}.justificativa", $r->resposta?->justificativa) }}</textarea>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </fieldset>
                @unless ($travado)
                    <div class="text-end"><button class="btn btn-primary">Salvar respostas</button></div>
                @endunless
            </form>
        @endif
    </div>
</x-app-layout>
