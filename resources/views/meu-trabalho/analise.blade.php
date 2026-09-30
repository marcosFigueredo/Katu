<x-app-layout>
    @php
        $doc     = $analise->trabalhoDocumento;
        $ollama  = $analise->ollama;
        $res     = $ollama?->resposta; // array ou null
        $tipoLabel = match($doc->tipo_documento) {
            'qualificacao' => 'Exame de Qualificação',
            'dissertacao'  => 'Dissertação',
            'tese'         => 'Tese',
            default        => $doc->tipo_documento,
        };
    @endphp

    <div class="container-fluid">

        {{-- Cabeçalho --}}
        <div class="d-flex align-items-center gap-3 mb-4">
            <a href="{{ route('meu-trabalho.index') }}" class="btn btn-sm btn-outline-secondary">
                ← Voltar
            </a>
            <div>
                <h1 class="h3 mb-0 fw-bold text-dark">Resultado da Análise</h1>
                <p class="text-muted small mb-0">
                    {{ $tipoLabel }} · v{{ str_pad($doc->versao, 2, '0', STR_PAD_LEFT) }} ·
                    Enviado em {{ $doc->enviado_em?->format('d/m/Y \à\s H:i') }}
                </p>
            </div>
        </div>

        <div class="row g-4">

            {{-- ── Resumo geral da análise estrutural ──────────────────────── --}}
            <div class="col-12">
                @php
                    $corGeral = match($analise->status_geral) {
                        'conforme'       => 'success',
                        'com_alertas'    => 'warning',
                        'com_pendencias' => 'danger',
                        default          => 'secondary',
                    };
                    $labelGeral = match($analise->status_geral) {
                        'conforme'       => 'Conforme',
                        'com_alertas'    => 'Com alertas',
                        'com_pendencias' => 'Com pendências',
                        'falha'          => 'Falhou',
                        default          => '—',
                    };
                @endphp
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div>
                                <h2 class="h5 fw-bold text-dark mb-1">Análise Formal do Documento</h2>
                                <p class="text-muted small mb-0">
                                    Verificação automática de estrutura, capa e elementos obrigatórios.
                                </p>
                            </div>
                            <span class="badge text-bg-{{ $corGeral }} fs-6 px-3 py-2">
                                {{ $labelGeral }}
                            </span>
                        </div>

                        <hr class="my-3">

                        <div class="row g-3 text-center">
                            <div class="col-6 col-md-3">
                                <div class="bg-light rounded p-3">
                                    <div class="h2 fw-bold text-dark mb-0">
                                        {{ $analise->percentual_conformidade }}%
                                    </div>
                                    <div class="small text-muted">Conformidade</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-light rounded p-3">
                                    <div class="h2 fw-bold text-success mb-0">
                                        {{ $analise->itens_conformes }}
                                    </div>
                                    <div class="small text-muted">Conformes</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-light rounded p-3">
                                    <div class="h2 fw-bold text-warning mb-0">
                                        {{ $analise->itens_atencao }}
                                    </div>
                                    <div class="small text-muted">Em atenção</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-light rounded p-3">
                                    <div class="h2 fw-bold text-danger mb-0">
                                        {{ $analise->itens_pendentes }}
                                    </div>
                                    <div class="small text-muted">Pendentes</div>
                                </div>
                            </div>
                        </div>

                        @if ($analise->mensagem_geral)
                            <p class="text-muted small mt-3 mb-0">
                                {{ $analise->mensagem_geral }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ── Card do Ollama ────────────────────────────────────────────── --}}
            <div class="col-12">
                <div class="card border-0 shadow-sm">

                    <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
                        <div>
                            <h2 class="h5 fw-bold text-dark mb-0">
                                Avaliação do Resumo
                                <span class="badge text-bg-secondary fw-normal ms-2" style="font-size: 11px;">IA</span>
                            </h2>
                            <p class="text-muted small mb-0">
                                Análise de conteúdo gerada automaticamente para orientar a revisão do resumo.
                            </p>
                        </div>
                        @if ($ollama)
                            <div class="text-end text-muted small">
                                <div>{{ $ollama->modelo }}</div>
                                <div>
                                    @if ($ollama->status === 'concluida')
                                        {{ $ollama->gerado_em?->format('d/m/Y H:i') }}
                                    @elseif ($ollama->status === 'processando')
                                        <span class="text-warning">Processando…</span>
                                    @else
                                        <span class="text-danger">Falhou</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="card-body">

                        {{-- Sem registro Ollama --}}
                        @if (! $ollama)
                            <p class="text-muted mb-0">A avaliação do resumo não foi executada para este documento.</p>

                        {{-- Falhou --}}
                        @elseif ($ollama->status === 'falhou')
                            <div class="alert alert-warning mb-0">
                                <strong>Não foi possível avaliar o resumo automaticamente.</strong><br>
                                <span class="small text-muted">{{ $ollama->erro }}</span>
                            </div>

                        {{-- Processando --}}
                        @elseif ($ollama->status === 'processando')
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary mb-3" role="status" style="width:2.5rem;height:2.5rem;">
                                    <span class="visually-hidden">Processando…</span>
                                </div>
                                <p class="fw-semibold mb-1" style="color:#00303A;">Avaliando resumo com inteligência artificial…</p>
                                <p class="text-muted small mb-0">Aguarde, isso pode levar alguns instantes.</p>
                            </div>
                            <script>
                                fetch("{{ route('meu-trabalho.avaliar-resumo', $analise) }}", {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json',
                                    },
                                }).finally(() => location.reload());
                            </script>

                        {{-- Concluída --}}
                        @else
                            <div class="row g-4">

                                {{-- Coluna esquerda: clareza + aderência --}}
                                <div class="col-md-4">

                                    {{-- Clareza --}}
                                    <div class="mb-4">
                                        <div class="text-muted small fw-semibold text-uppercase mb-2" style="letter-spacing:.05em;">
                                            Clareza do Resumo
                                        </div>
                                        @php
                                            $clareza = $res['clareza'] ?? null;
                                            $clarezaCor = match($clareza) {
                                                'adequada'    => 'success',
                                                'parcial'     => 'warning',
                                                'insuficiente'=> 'danger',
                                                default       => 'secondary',
                                            };
                                            $clarezaLabel = match($clareza) {
                                                'adequada'    => 'Adequada',
                                                'parcial'     => 'Parcial',
                                                'insuficiente'=> 'Insuficiente',
                                                default       => '—',
                                            };
                                        @endphp
                                        <span class="badge text-bg-{{ $clarezaCor }} fs-6 px-3 py-2">
                                            {{ $clarezaLabel }}
                                        </span>
                                    </div>

                                    {{-- Aderência ao programa --}}
                                    <div>
                                        <div class="text-muted small fw-semibold text-uppercase mb-2" style="letter-spacing:.05em;">
                                            Aderência ao PPGMSB
                                        </div>
                                        @php
                                            $aderencia = $res['aderencia_ao_programa'] ?? null;
                                            $aderenciaCor = match($aderencia) {
                                                'aparente'     => 'success',
                                                'parcial'      => 'warning',
                                                'não aparente' => 'danger',
                                                default        => 'secondary',
                                            };
                                            $aderenciaLabel = match($aderencia) {
                                                'aparente'     => 'Aparente',
                                                'parcial'      => 'Parcial',
                                                'não aparente' => 'Não aparente',
                                                default        => '—',
                                            };
                                        @endphp
                                        <span class="badge text-bg-{{ $aderenciaCor }} fs-6 px-3 py-2">
                                            {{ $aderenciaLabel }}
                                        </span>
                                    </div>

                                </div>

                                {{-- Coluna central: elementos do resumo --}}
                                <div class="col-md-4">
                                    <div class="text-muted small fw-semibold text-uppercase mb-3" style="letter-spacing:.05em;">
                                        Elementos Identificados
                                    </div>

                                    @php
                                        $elementos = [
                                            'objetivo_identificado'                          => 'Objetivo / problema de pesquisa',
                                            'metodo_identificado'                            => 'Metodologia / abordagem',
                                            'resultados_ou_resultados_esperados_identificados'=> 'Resultados ou resultados esperados',
                                            'conclusao_ou_contribuicao_identificada'         => 'Conclusão / contribuição científica',
                                        ];
                                    @endphp

                                    <ul class="list-unstyled mb-0">
                                        @foreach ($elementos as $chave => $rotulo)
                                            @php $presente = $res[$chave] ?? false; @endphp
                                            <li class="d-flex align-items-start gap-2 mb-2">
                                                @if ($presente)
                                                    <svg class="flex-shrink-0 text-success" style="width:18px;height:18px;margin-top:2px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                                    </svg>
                                                @else
                                                    <svg class="flex-shrink-0 text-danger" style="width:18px;height:18px;margin-top:2px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                @endif
                                                <span class="small {{ $presente ? 'text-dark' : 'text-muted' }}">
                                                    {{ $rotulo }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                                {{-- Coluna direita: palavras-chave --}}
                                <div class="col-md-4">
                                    <div class="text-muted small fw-semibold text-uppercase mb-3" style="letter-spacing:.05em;">
                                        Palavras-chave
                                    </div>

                                    @php
                                        $kwQtd       = $res['palavras_chave_quantidade'] ?? 0;
                                        $kwIdent     = $res['palavras_chave_identificadas'] ?? false;
                                        $kwAdequadas = $res['palavras_chave_adequadas_ao_resumo'] ?? false;
                                        $kwObs       = $res['palavras_chave_observacao'] ?? '';
                                    @endphp

                                    <p class="mb-2">
                                        <span class="small text-muted">Quantidade informada:</span><br>
                                        <strong>{{ $kwQtd }} {{ $kwQtd === 1 ? 'palavra-chave' : 'palavras-chave' }}</strong>
                                    </p>

                                    <p class="mb-2">
                                        <span class="small text-muted">Refletem o conteúdo:</span><br>
                                        @if ($kwIdent)
                                            <span class="text-success fw-semibold small">Sim</span>
                                        @else
                                            <span class="text-danger fw-semibold small">Não identificadas</span>
                                        @endif
                                    </p>

                                    <p class="mb-0">
                                        <span class="small text-muted">Adequadas ao resumo:</span><br>
                                        @if ($kwAdequadas)
                                            <span class="text-success fw-semibold small">Sim</span>
                                        @else
                                            <span class="text-danger fw-semibold small">Não</span>
                                        @endif
                                    </p>

                                    @if ($kwObs)
                                        <p class="small text-muted mt-2 mb-0">{{ $kwObs }}</p>
                                    @endif
                                </div>

                                {{-- Observações para o estudante (largura total) --}}
                                @php $observacoes = $res['observacoes'] ?? []; @endphp
                                @if (! empty($observacoes))
                                    <div class="col-12">
                                        <hr class="mt-0">
                                        <div class="text-muted small fw-semibold text-uppercase mb-3" style="letter-spacing:.05em;">
                                            Sugestões para Revisão
                                        </div>
                                        <ul class="list-unstyled mb-0">
                                            @foreach ($observacoes as $obs)
                                                <li class="d-flex align-items-start gap-2 mb-2">
                                                    <svg class="flex-shrink-0 text-primary" style="width:16px;height:16px;margin-top:3px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"/>
                                                    </svg>
                                                    <span class="small text-dark">{{ $obs }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                            </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- ── Conformidade com as Normas (IA) ─────────────────────────────── --}}
            @php $conformidades = $analise->conformidadeRag ?? collect(); @endphp
            @if ($conformidades->isNotEmpty())
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom py-3">
                            <h2 class="h5 fw-bold text-dark mb-0">
                                Conformidade com as Normas
                                <span class="badge text-bg-secondary fw-normal ms-2" style="font-size: 11px;">IA</span>
                            </h2>
                            <p class="text-muted small mb-0">
                                Avaliação automática à luz do manual de normas do programa. Não bloqueia o envio à banca.
                            </p>
                        </div>
                        <div class="card-body">
                            @php
                                $titulos = [
                                    'aderencia' => 'Seção "Aderência"',
                                    'ods' => 'ODS do Trabalho',
                                    'citacao_apa' => 'Padrão de citação (APA)',
                                    'uso_ia' => 'Uso de Inteligência Artificial',
                                ];
                                $ordemChaves = array_keys($titulos);
                                $ordenadas = $conformidades->sortBy(fn ($c) => array_search($c->verificacao, $ordemChaves) ?: 99);
                                $processando = $conformidades->contains(fn ($c) => $c->status === 'processando');
                            @endphp

                            @if ($processando)
                                <div class="alert alert-info small mb-3">
                                    <i class="bi bi-hourglass-split me-2"></i>
                                    Análise em processamento. Recarregue a página em instantes.
                                </div>
                            @endif

                            <div class="row g-3">
                                @foreach ($ordenadas as $c)
                                    @php
                                        $cor = match($c->veredito) {
                                            'conforme' => 'success',
                                            'parcial' => 'warning',
                                            'nao_conforme' => 'danger',
                                            default => 'secondary',
                                        };
                                        $label = match($c->veredito) {
                                            'conforme' => 'Conforme',
                                            'parcial' => 'Parcial',
                                            'nao_conforme' => 'Não conforme',
                                            default => $c->status === 'falhou' ? 'Não avaliado' : 'Processando…',
                                        };
                                    @endphp
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded-3 h-100">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <span class="fw-semibold small">{{ $titulos[$c->verificacao] ?? $c->verificacao }}</span>
                                                <span class="badge text-bg-{{ $cor }}">{{ $label }}</span>
                                            </div>
                                            @if ($c->justificativa)
                                                <p class="small text-dark mb-1">{{ $c->justificativa }}</p>
                                            @endif
                                            @if ($c->trecho_norma_citado)
                                                <p class="small text-muted fst-italic mb-0">"{{ $c->trecho_norma_citado }}"</p>
                                            @endif
                                            @if ($c->status === 'falhou' && $c->erro)
                                                <p class="small text-danger mb-0">{{ $c->erro }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── Itens detalhados (colapsável) ────────────────────────────── --}}
            @php
                $grupos = [
                    'capa'        => 'Capa',
                    'tecnico'     => 'Técnico',
                    'pre_textual' => 'Pré-textuais',
                    'textual'     => 'Textuais',
                    'pos_textual' => 'Pós-textuais',
                    'metadados'   => 'Metadados',
                ];
                $itensPorGrupo = $analise->itens->groupBy('grupo');
            @endphp

            @if ($analise->itens->isNotEmpty())
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">

                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h2 class="h5 fw-bold text-dark mb-0">Itens Verificados</h2>
                                <button class="btn btn-sm btn-outline-secondary"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#itensDetalhados">
                                    Expandir / Recolher
                                </button>
                            </div>

                            <div class="collapse" id="itensDetalhados">
                                @foreach ($grupos as $chaveGrupo => $nomeGrupo)
                                    @if ($itensPorGrupo->has($chaveGrupo))
                                        <div class="mb-4">
                                            <div class="text-muted small fw-semibold text-uppercase mb-2" style="letter-spacing:.05em;">
                                                {{ $nomeGrupo }}
                                            </div>
                                            <ul class="list-unstyled mb-0">
                                                @foreach ($itensPorGrupo[$chaveGrupo] as $item)
                                                    @php
                                                        $icone = match($item->status) {
                                                            'conforme'     => ['cor' => 'success', 'path' => 'M4.5 12.75l6 6 9-13.5'],
                                                            'atencao'      => ['cor' => 'warning', 'path' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z'],
                                                            'pendente'     => ['cor' => 'danger',  'path' => 'M6 18L18 6M6 6l12 12'],
                                                            default        => ['cor' => 'secondary','path' => 'M18 12H6'],
                                                        };
                                                    @endphp
                                                    <li class="d-flex align-items-start gap-2 mb-2">
                                                        <svg class="flex-shrink-0 text-{{ $icone['cor'] }}" style="width:16px;height:16px;margin-top:3px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone['path'] }}"/>
                                                        </svg>
                                                        <div>
                                                            <span class="small fw-semibold text-dark">{{ $item->nome_item }}</span>
                                                            @if ($item->pagina_detectada)
                                                                <span class="text-muted small"> · p. {{ $item->pagina_detectada }}</span>
                                                            @endif
                                                            @if ($item->mensagem && $item->status !== 'conforme')
                                                                <div class="text-muted" style="font-size:12px;">{{ $item->mensagem }}</div>
                                                            @endif
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
