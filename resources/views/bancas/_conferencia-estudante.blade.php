@use('App\Models\AtividadeFormativa')
@use('App\Models\AtividadeRealizada')
@use('App\Models\ProducaoPublicacao')
{{-- Conferência da Secretaria: atividades formativas (qualificação e defesa) e publicações (defesa). --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h2 class="h6 fw-bold mb-0" style="color:#00303A;">
            <i class="bi bi-mortarboard me-1"></i>Atividades formativas
        </h2>
        <span @class(['badge', 'text-bg-success' => $analise['atividades_ok'], 'text-bg-warning' => ! $analise['atividades_ok']])>
            {{ $analise['atividades_ok'] ? 'Conferidas' : 'Conferência pendente' }}
        </span>
    </div>
    <div class="card-body">
        @if ($atividades->isEmpty())
            <p class="text-muted small mb-0">Nenhuma atividade formativa exigida neste pedido.</p>
        @else
            <form method="POST" action="{{ route('bancas.homologacao.atividades', $banca) }}">
                @csrf
                @foreach ($atividades as $atividade)
                    @php
                        $meus = $lancamentos->get($atividade->id, collect());
                        $confirmadas = $meus->where('conferencia', 'confirmada')->count();
                    @endphp
                    <div class="border rounded p-2 mb-2">
                        <div class="d-flex justify-content-between">
                            <strong>{{ $atividade->nome }}</strong>
                            <span class="small">{{ $confirmadas }} de {{ $atividade->quantidade_exigida }} confirmada(s)
                                · informada por {{ mb_strtolower(AtividadeFormativa::INFORMADA_POR[$atividade->informada_por]) }}</span>
                        </div>
                        @foreach ($meus as $lancamento)
                            <div class="d-flex flex-wrap justify-content-between align-items-center small border-top mt-2 pt-2 gap-2">
                                <div>
                                    {{ $lancamento->descricao }}
                                    @if ($lancamento->link)
                                        · <a href="{{ $lancamento->link }}" target="_blank" rel="noopener">comprovante</a>
                                    @endif
                                </div>
                                <div class="btn-group btn-group-sm" role="group">
                                    @foreach (AtividadeRealizada::CONFERENCIA as $valor => $rotulo)
                                        <input type="radio" class="btn-check" name="conferencias[{{ $lancamento->id }}]" value="{{ $valor }}"
                                               id="conf-{{ $lancamento->id }}-{{ $valor }}" @checked($lancamento->conferencia === $valor) @disabled($somenteLeitura)>
                                        <label class="btn {{ $valor === 'confirmada' ? 'btn-outline-success' : 'btn-outline-danger' }}" for="conf-{{ $lancamento->id }}-{{ $valor }}">{{ $rotulo }}</label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        @if ($atividade->informada_por === 'secretaria' && $confirmadas < $atividade->quantidade_exigida && ! $somenteLeitura)
                            <div class="form-check small mt-2">
                                <input class="form-check-input" type="checkbox" name="registrar[{{ $atividade->id }}]" value="1" id="registrar-{{ $atividade->id }}">
                                <label class="form-check-label" for="registrar-{{ $atividade->id }}">Registrar como cumprida (a Secretaria confirma)</label>
                            </div>
                        @endif
                    </div>
                @endforeach
                @unless ($somenteLeitura)
                    <div class="text-end"><button class="btn btn-outline-primary btn-sm"><i class="bi bi-save me-1"></i>Salvar conferência</button></div>
                @endunless
            </form>
        @endif
    </div>
</div>

@if ($banca->tipo === 'defesa')
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h2 class="h6 fw-bold mb-0" style="color:#00303A;">
                <i class="bi bi-journal-text me-1"></i>Publicações da produção associada
            </h2>
            <span @class(['badge', 'text-bg-success' => $analise['publicacoes_ok'], 'text-bg-warning' => ! $analise['publicacoes_ok']])>
                {{ $analise['publicacoes_ok'] ? 'Conferidas' : 'Conferência pendente' }}
            </span>
        </div>
        <div class="card-body">
            @if ($publicacoes->isEmpty())
                <p class="text-muted small mb-0">O estudante não informou publicações.</p>
            @else
                <form method="POST" action="{{ route('bancas.homologacao.publicacoes', $banca) }}">
                    @csrf
                    @foreach ($publicacoes as $p)
                        <div class="border rounded p-2 mb-2">
                            <div class="fw-semibold">{{ $p->titulo }}</div>
                            <div class="small text-muted mb-2">
                                {{ ProducaoPublicacao::TIPOS[$p->tipo] }} · {{ $p->veiculo }} · {{ $p->ano }}
                                @if ($p->doi) · <a href="https://doi.org/{{ $p->doi }}" target="_blank" rel="noopener">DOI {{ $p->doi }}</a> @endif
                                <div class="mt-1">@include('partials.selo-openalex', ['publicacao' => $p])</div>
                            </div>
                            <div class="d-flex flex-wrap gap-3 align-items-center small">
                                <select name="publicacoes[{{ $p->id }}][situacao]" class="form-select form-select-sm w-auto" @disabled($somenteLeitura) required>
                                    <option value="">Situação…</option>
                                    @foreach (ProducaoPublicacao::SITUACOES as $v => $r)
                                        <option value="{{ $v }}" @selected($p->situacao === $v)>{{ $r }}</option>
                                    @endforeach
                                </select>
                                @foreach (ProducaoPublicacao::MARCACOES as $campo => $rotulo)
                                    <div class="form-check mb-0">
                                        <input type="hidden" name="publicacoes[{{ $p->id }}][{{ $campo }}]" value="0">
                                        <input class="form-check-input" type="checkbox" name="publicacoes[{{ $p->id }}][{{ $campo }}]" value="1"
                                               id="pub-{{ $p->id }}-{{ $campo }}" @checked($p->{$campo}) @disabled($somenteLeitura)>
                                        <label class="form-check-label" for="pub-{{ $p->id }}-{{ $campo }}">{{ $rotulo }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    @unless ($somenteLeitura)
                        <div class="text-end"><button class="btn btn-outline-primary btn-sm"><i class="bi bi-save me-1"></i>Salvar publicações</button></div>
                    @endunless
                </form>
            @endif
        </div>
    </div>
@endif
