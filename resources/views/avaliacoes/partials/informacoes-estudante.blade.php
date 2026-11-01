@use('App\Models\TrabalhoDadosPesquisa')
@use('App\Models\TrabalhoInformacoesAdicionais')
@use('App\Models\ProducaoPublicacao')
@use('App\Models\ProducaoExtensao')
@use('App\Models\ProducaoPopularizacao')
@use('App\Models\AtividadeRealizada')
{{-- O que o estudante declarou no pedido (a banca vê; o espelho nunca aparece aqui). Recebe $banca. --}}
@php
    $trabalhoInfo = $banca->trabalhoAcademico;
    $copia = $banca->dados_pesquisa_enviados;
    $ehDefesa = $banca->tipo === 'defesa';
    $atividadesInfo = app(\App\Services\AtividadesFormativasService::class)->exigidas($trabalhoInfo, $banca->tipo);
    $lancamentosInfo = $trabalhoInfo->atividadesRealizadas()->get()->groupBy('atividade_formativa_id');
    $impactoInfo = $ehDefesa ? $trabalhoInfo->impacto : null;
    $infoAdicional = $ehDefesa ? $trabalhoInfo->informacoesAdicionais : null;
    $tiposImpacto = ['cientifico' => 'Científico', 'metodologico' => 'Metodológico', 'tecnologico' => 'Tecnológico',
        'social' => 'Social', 'ambiental' => 'Ambiental', 'formativo' => 'Formativo', 'institucional' => 'Institucional'];
    $simNao = fn ($v) => $v ? 'Sim' : 'Não';
@endphp
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header py-2 px-3 bg-white d-flex justify-content-between align-items-center"
         role="button" data-bs-toggle="collapse" data-bs-target="#informacoes-estudante">
        <h2 class="h6 fw-bold mb-0" style="color:#00303A;"><i class="bi bi-person-lines-fill me-1"></i>Informações do estudante</h2>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div class="collapse show" id="informacoes-estudante">
        <div class="card-body small">

            <h3 class="h6 fw-semibold">Dados da pesquisa</h3>
            @if (! $copia)
                <p class="text-muted">Dados da pesquisa não enviados com este pedido.</p>
            @else
                <ul class="mb-3">
                    <li>Código: {{ TrabalhoDadosPesquisa::CODIGO[$copia['codigo'] ?? ''] ?? '—' }}
                        @if (! empty($copia['codigo_link'])) — <a href="{{ $copia['codigo_link'] }}" target="_blank" rel="noopener">{{ $copia['codigo_link'] }}</a> @endif
                        @if (! empty($copia['codigo_justificativa'])) — {{ $copia['codigo_justificativa'] }} @endif
                    </li>
                    <li>Dados: {{ TrabalhoDadosPesquisa::DADOS_DISPONIBILIDADE[$copia['dados_disponibilidade'] ?? ''] ?? '—' }}
                        @if (! empty($copia['dados_tipos']))
                            ({{ collect($copia['dados_tipos'])->map(fn ($t) => TrabalhoDadosPesquisa::DADOS_TIPOS[$t] ?? $t)->implode('; ') }})
                        @endif
                        @if (! empty($copia['dados_link'])) — <a href="{{ $copia['dados_link'] }}" target="_blank" rel="noopener">{{ $copia['dados_link'] }}</a> @endif
                        @if (! empty($copia['dados_justificativa'])) — {{ $copia['dados_justificativa'] }} @endif
                    </li>
                    <li>Seres humanos: {{ $simNao($copia['seres_humanos'] ?? false) }}
                        @if (! empty($copia['seres_humanos']))
                            — CAAE {{ $copia['caae'] ?? '—' }}, parecer nº {{ $copia['parecer_numero'] ?? '—' }},
                            {{ TrabalhoDadosPesquisa::TCLE[$copia['tcle'] ?? ''] ?? '—' }}
                            @if (! empty($copia['tcle_justificativa'])) ({{ $copia['tcle_justificativa'] }}) @endif
                        @endif
                    </li>
                    <li>Animais: {{ $simNao($copia['animais'] ?? false) }}
                        @if (! empty($copia['animais'])) — protocolo {{ $copia['ceua_protocolo'] ?? '—' }} @endif
                    </li>
                </ul>
            @endif

            <h3 class="h6 fw-semibold">Atividades formativas</h3>
            @if ($atividadesInfo->isEmpty())
                <p class="text-muted">Nenhuma atividade exigida neste pedido.</p>
            @else
                <ul class="mb-3">
                    @foreach ($atividadesInfo as $atividade)
                        @php $meus = $lancamentosInfo->get($atividade->id, collect()); @endphp
                        <li>{{ $atividade->nome }} — {{ $meus->where('conferencia', 'confirmada')->count() }} de {{ $atividade->quantidade_exigida }} confirmada(s)
                            @if ($meus->isNotEmpty())
                                <ul>
                                    @foreach ($meus as $l)
                                        <li>{{ $l->descricao }}
                                            @if ($l->link) · <a href="{{ $l->link }}" target="_blank" rel="noopener">comprovante</a> @endif
                                            · {{ $l->conferencia ? AtividadeRealizada::CONFERENCIA[$l->conferencia] : 'aguardando conferência' }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($ehDefesa)
                <h3 class="h6 fw-semibold">Tipos de impacto declarados</h3>
                @php $aplicaveis = $impactoInfo ? collect($tiposImpacto)->filter(fn ($r, $k) => $impactoInfo->{"impacto_{$k}_aplicavel"}) : collect(); @endphp
                @if ($aplicaveis->isEmpty())
                    <p class="text-muted">Nenhum tipo de impacto declarado.</p>
                @else
                    <ul class="mb-3">
                        @foreach ($aplicaveis as $k => $rotulo)
                            <li><strong>{{ $rotulo }}:</strong> {{ $impactoInfo->{"impacto_{$k}_descricao"} }}</li>
                        @endforeach
                    </ul>
                @endif

                <h3 class="h6 fw-semibold">Produção associada</h3>
                <ul class="mb-3">
                    @forelse ($trabalhoInfo->publicacoes()->orderByDesc('ano')->get() as $p)
                        <li>{{ $p->titulo }} — {{ ProducaoPublicacao::TIPOS[$p->tipo] }}, {{ $p->veiculo }}, {{ $p->ano }}
                            @if ($p->doi) · <a href="https://doi.org/{{ $p->doi }}" target="_blank" rel="noopener">DOI</a> @endif
                            @if ($p->situacao) · {{ ProducaoPublicacao::SITUACOES[$p->situacao] }} @endif</li>
                    @empty
                        <li class="text-muted">Nenhuma publicação.</li>
                    @endforelse
                    @foreach ($trabalhoInfo->extensoes as $e)
                        <li>Extensão: {{ $e->titulo }} — {{ ProducaoExtensao::TIPOS[$e->tipo] }}, {{ $e->parceiro }}, {{ $e->publico }} pessoas</li>
                    @endforeach
                    @foreach ($trabalhoInfo->popularizacoes as $pp)
                        <li>Popularização: {{ $pp->titulo }} — {{ ProducaoPopularizacao::TIPOS[$pp->tipo] }}, {{ $pp->veiculo }}, alcance {{ $pp->alcance }}</li>
                    @endforeach
                </ul>

                <h3 class="h6 fw-semibold">Informações adicionais</h3>
                @if (! $infoAdicional)
                    <p class="text-muted mb-0">Não respondidas.</p>
                @else
                    <ul class="mb-0">
                        <li>Conflito de interesses: {{ $simNao($infoAdicional->conflito_interesses) }}@if ($infoAdicional->conflito_descricao) — {{ $infoAdicional->conflito_descricao }}@endif</li>
                        <li>Disponibilidade de dados: {{ TrabalhoInformacoesAdicionais::DISPONIBILIDADE[$infoAdicional->disponibilidade_dados] ?? '—' }}
                            @if ($infoAdicional->dados_repositorio) — <a href="{{ $infoAdicional->dados_repositorio }}" target="_blank" rel="noopener">{{ $infoAdicional->dados_repositorio }}</a> @endif</li>
                        <li>Amostras físicas: {{ $simNao($infoAdicional->amostras_fisicas) }}@if ($infoAdicional->amostras_declaracao) — {{ $infoAdicional->amostras_declaracao }}@endif</li>
                        <li>Aprovação ética: {{ $simNao($infoAdicional->aprovacao_etica) }}
                            @if ($infoAdicional->aprovacao_etica_path) — <a href="{{ route('bancas.aprovacao-etica', [$banca, 'documento' => 'aprovacao']) }}">documento de aprovação (PDF)</a> @endif</li>
                        <li>Participantes humanos ou animais: {{ $simNao($infoAdicional->participantes) }}
                            @if ($infoAdicional->certidao_etica_path) — <a href="{{ route('bancas.aprovacao-etica', [$banca, 'documento' => 'certidao']) }}">certidão do comitê de ética (PDF)</a> @endif</li>
                        <li>Uso de IA generativa: {{ $simNao($infoAdicional->uso_ia) }}
                            @if ($infoAdicional->uso_ia) — {{ $infoAdicional->ia_ferramentas }}; {{ $infoAdicional->ia_etapas }} @endif</li>
                        <li>Declaração de originalidade: {{ $infoAdicional->originalidade_confirmada ? 'confirmada' : 'não confirmada' }}</li>
                    </ul>
                @endif
            @endif
        </div>
    </div>
</div>
