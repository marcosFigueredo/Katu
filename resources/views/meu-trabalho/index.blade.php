<x-app-layout>
    <div class="container-fluid">

        <div class="d-flex align-items-start justify-content-between mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1" style="color: #00303A;">Meu Trabalho</h1>
                <p class="text-muted mb-0">Acompanhe sua banca e envie seu documento para análise.</p>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- ── Bancas agendadas ─────────────────────────────────────────── --}}
        @if ($bancas->isNotEmpty())
            <h2 class="h6 fw-bold text-uppercase text-muted mb-3">
                <i class="bi bi-person-workspace me-2"></i>Minha(s) Banca(s)
            </h2>
            <div class="row g-3 mb-4">
                @foreach ($bancas as $banca)
                    @php
                        $tipoCor   = $banca->tipo === 'qualificacao' ? 'info'      : 'primary';
                        $tipoLabel = $banca->tipo === 'qualificacao' ? 'Qualificação' : 'Defesa';
                        $statusCor = match($banca->status) {
                            'cadastrada'   => 'secondary',
                            'em_avaliacao' => 'warning',
                            'finalizada'   => 'success',
                            'cancelada'    => 'danger',
                            default        => 'secondary',
                        };
                        $statusLabel = match($banca->status) {
                            'cadastrada'   => 'Agendada',
                            'em_avaliacao' => 'Em avaliação',
                            'finalizada'   => 'Finalizada',
                            'cancelada'    => 'Cancelada',
                            default        => $banca->status,
                        };
                        $orientador = $trabalho?->orientacoes?->firstWhere('tipo', 'orientador');
                        $coorientadores = $trabalho?->orientacoes?->where('tipo', 'coorientador') ?? collect();
                        $homologacaoCor = match($banca->homologacao_status) {
                            'homologada' => 'success',
                            'devolvida'  => 'danger',
                            'proposta'   => 'info',
                            default      => 'secondary',
                        };
                        $homologacaoLabel = match($banca->homologacao_status) {
                            'proposta'   => 'Proposta — envie o trabalho',
                            'aguardando' => 'Aguardando homologação',
                            'devolvida'  => 'Devolvida — veja a não conformidade',
                            'homologada' => 'Homologada',
                            default      => $banca->homologacao_status,
                        };
                    @endphp
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="badge text-bg-{{ $tipoCor }} fs-6 px-3 py-2">
                                        {{ $tipoLabel }}
                                    </span>
                                    <span class="badge text-bg-{{ $statusCor }}">{{ $statusLabel }}</span>
                                </div>
                                <div class="mb-3">
                                    <span class="badge text-bg-{{ $homologacaoCor }}">
                                        <i class="bi bi-patch-check-fill me-1"></i>{{ $homologacaoLabel }}
                                    </span>
                                </div>

                                <dl class="row small mb-0">
                                    @if ($banca->data_hora)
                                        <dt class="col-5 text-muted fw-normal">Data e hora</dt>
                                        <dd class="col-7 fw-semibold mb-2">
                                            {{ $banca->data_hora->format('d/m/Y') }}
                                            às {{ $banca->data_hora->format('H:i') }}
                                        </dd>
                                    @else
                                        <dt class="col-5 text-muted fw-normal">Data e hora</dt>
                                        <dd class="col-7 mb-2 text-muted">A definir</dd>
                                    @endif

                                    @if ($banca->local)
                                        <dt class="col-5 text-muted fw-normal">Local</dt>
                                        <dd class="col-7 mb-2">{{ $banca->local }}</dd>
                                    @endif

                                    @if ($orientador)
                                        <dt class="col-5 text-muted fw-normal">Orientador</dt>
                                        <dd class="col-7 mb-2">
                                            {{ $orientador->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '—' }}
                                        </dd>
                                    @endif

                                    @if ($coorientadores->isNotEmpty())
                                        <dt class="col-5 text-muted fw-normal">Coorientador(es)</dt>
                                        <dd class="col-7 mb-0">
                                            @foreach ($coorientadores as $co)
                                                <div>{{ $co->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '—' }}</div>
                                            @endforeach
                                        </dd>
                                    @endif
                                </dl>

                                @if ($banca->homologacao_status === 'aguardando')
                                    <div class="alert alert-secondary small mt-3 mb-0">
                                        <i class="bi bi-hourglass-split me-1"></i>
                                        Pedido na fila da Secretaria{{ (int) $banca->homologacao_tentativa > 1 ? ' (tentativa ' . $banca->homologacao_tentativa . ')' : '' }}.
                                        A banca e o trabalho não podem ser alterados até a resposta.
                                    </div>
                                @endif

                                @if (in_array($banca->homologacao_status, ['proposta', 'devolvida'], true))
                                    @if ($banca->homologacao_status === 'devolvida' && $banca->homologacao_observacoes)
                                        <div class="alert alert-danger small mt-3 mb-2">
                                            <strong>Não conformidade indicada pela Secretaria:</strong> {{ $banca->homologacao_observacoes }}
                                        </div>
                                    @endif
                                    <a href="{{ route('bancas.edit', $banca) }}" class="btn btn-sm btn-outline-primary mt-2">
                                        <i class="bi bi-pencil-fill me-1"></i>
                                        {{ $banca->homologacao_status === 'devolvida' ? 'Corrigir e reenviar' : 'Editar proposta' }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="alert alert-warning mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <i class="bi bi-hourglass-split me-2"></i>
                    Nenhuma banca proposta ainda. Proponha a banca, envie o trabalho e envie o pedido à Secretaria para homologação.
                </div>
                @if ($trabalho)
                    <a href="{{ route('bancas.create') }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>Propor banca
                    </a>
                @endif
            </div>
        @endif

        {{-- ── Envio + Histórico ────────────────────────────────────────── --}}
        <div class="row g-4">

            {{-- Formulário de envio --}}
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent py-3">
                        <h2 class="h6 fw-bold mb-0">
                            <i class="bi bi-cloud-upload-fill me-2" style="color:#00303A;"></i>Enviar Documento
                        </h2>
                    </div>
                    <div class="card-body">

                        @if (! $bancaPedido)
                            <div class="alert alert-warning small mb-0">
                                <i class="bi bi-lock-fill me-2"></i>
                                @if ($bancas->contains(fn ($b) => $b->homologacao_status === 'aguardando'))
                                    O pedido está na fila da Secretaria. Aguarde a homologação ou a devolução.
                                @else
                                    Proponha a banca antes de enviar o trabalho.
                                @endif
                            </div>
                        @else

                        @php
                            $tipoDocumentoEnvio = \App\Http\Controllers\SubmissaoLatexController::tipoDocumentoDaBanca($bancaPedido, $trabalho);
                        @endphp

                        {{-- O discente envia só o projeto LaTeX; título, resumo, palavras-chave e demais elementos saem do próprio projeto --}}
                        <p class="small text-muted">
                            Envie o projeto LaTeX completo em <strong>.zip</strong> (o .tex principal com <code>\documentclass</code>, bibliografia e figuras).
                            O sistema compila, extrai título, resumo, palavras-chave, autor, orientação e banca, confere com o cadastro
                            e gera o PDF da banca com linhas numeradas.
                        </p>
                        @if ($temTemplateLatex)
                            <a href="{{ route('programa-template.download', $programa) }}" class="btn btn-sm btn-outline-secondary mb-3">
                                <i class="bi bi-download me-1"></i>Baixar o template LaTeX do programa
                            </a>
                        @endif

                        <form id="form-envio-latex"
                              method="POST"
                              action="{{ route('meu-trabalho.submissao-latex.store') }}"
                              enctype="multipart/form-data">
                            @csrf

                            <div class="mb-3 small">
                                <span class="text-muted">Documento:</span>
                                <strong>{{ match ($tipoDocumentoEnvio) { 'qualificacao' => 'Exame de Qualificação', 'tese' => 'Tese', default => 'Dissertação' } }}</strong>
                                <span class="text-muted">— {{ $bancaPedido->tipo === 'defesa' ? 'banca de defesa' : 'banca de qualificação' }}</span>
                            </div>

                            <div class="mb-3">
                                <label for="zip" class="form-label fw-semibold">Projeto LaTeX (.zip)</label>
                                <input type="file"
                                       id="zip"
                                       name="zip"
                                       accept=".zip,application/zip"
                                       class="form-control @error('zip') is-invalid @enderror"
                                       required>
                                <div class="form-text">Tamanho máximo: 100 MB. Um único .tex principal (com \documentclass).</div>
                                @error('zip')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @error('tipo_documento')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>

                            <fieldset class="mb-3">
                                <legend class="form-label fw-semibold fs-6">Declarações</legend>
                                @foreach (\App\Models\TrabalhoSubmissaoLatex::DECLARACOES as $chaveDeclaracao => $textoDeclaracao)
                                    <div class="form-check mb-2">
                                        <input class="form-check-input @error('declaracoes.'.$chaveDeclaracao) is-invalid @enderror"
                                               type="checkbox" value="1" required
                                               id="declaracao-{{ $chaveDeclaracao }}" name="declaracoes[{{ $chaveDeclaracao }}]"
                                               @checked(old('declaracoes.'.$chaveDeclaracao))>
                                        <label class="form-check-label small" for="declaracao-{{ $chaveDeclaracao }}">{{ $textoDeclaracao }}</label>
                                        @error('declaracoes.'.$chaveDeclaracao)
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endforeach
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>A verificação prévia (compilação, elementos obrigatórios e conferência com o cadastro) é feita pelo próprio sistema após o envio.
                                </div>
                            </fieldset>

                            <button type="submit" class="btn btn-primary w-100 fw-semibold">
                                <i class="bi bi-file-earmark-zip-fill me-2"></i>Enviar projeto LaTeX
                            </button>
                        </form>

                        @endif
                    </div>
                </div>
            </div>

            {{-- Histórico de envios --}}
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent py-3">
                        <h2 class="h6 fw-bold mb-0">
                            <i class="bi bi-clock-history me-2" style="color:#00303A;"></i>Histórico de Envios
                        </h2>
                    </div>
                    <div class="card-body p-0">

                        @if ($submissoesLatex->isNotEmpty())
                            <div class="px-4 pt-3">
                                <h3 class="small fw-bold text-uppercase text-muted mb-2">Submissões LaTeX</h3>
                                <ul class="list-group list-group-flush mb-3">
                                    @foreach ($submissoesLatex as $sub)
                                        @php
                                            [$subCor, $subLabel] = match ($sub->status) {
                                                'ok'     => ['success', 'Ok'],
                                                'nao_ok' => ['danger', 'Não ok'],
                                                'erro'   => ['secondary', 'Erro'],
                                                default  => ['warning', 'Processando…'],
                                            };
                                        @endphp
                                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                                            <span class="small">
                                                {{ $sub->created_at?->format('d/m/Y H:i') }}
                                                @if ($loop->first)
                                                    <span class="badge text-bg-light border ms-1">vigente</span>
                                                @endif
                                            </span>
                                            <span class="d-flex align-items-center gap-2">
                                                <span class="badge text-bg-{{ $subCor }}">{{ $subLabel }}</span>
                                                <a href="{{ route('meu-trabalho.submissao-latex.show', $sub) }}" class="btn btn-sm btn-outline-primary">Ver verificação</a>
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($documentos->isEmpty())
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-file-earmark-pdf fs-1 d-block mb-2 opacity-30"></i>
                                Nenhum documento enviado ainda.
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4">Tipo</th>
                                            <th>Versão</th>
                                            <th>Enviado em</th>
                                            <th class="text-center">Status</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($documentos as $doc)
                                            @php
                                                $analise = $doc->ultimaAnalise;
                                                $statusCor = match($analise?->status_geral) {
                                                    'conforme'       => 'success',
                                                    'com_alertas'    => 'warning',
                                                    'com_pendencias' => 'danger',
                                                    'falha'          => 'secondary',
                                                    default          => 'light',
                                                };
                                                $statusLabel = match($analise?->status_geral) {
                                                    'conforme'       => 'Conforme',
                                                    'com_alertas'    => 'Com alertas',
                                                    'com_pendencias' => 'Com pendências',
                                                    'falha'          => 'Falhou',
                                                    default          => 'Aguardando',
                                                };
                                            @endphp
                                            <tr>
                                                <td class="ps-4">
                                                    <span class="fw-semibold">
                                                        {{ match($doc->tipo_documento) {
                                                            'qualificacao' => 'Qualificação',
                                                            'dissertacao'  => 'Dissertação',
                                                            'tese'         => 'Tese',
                                                            default        => $doc->tipo_documento,
                                                        } }}
                                                    </span>
                                                </td>
                                                <td class="text-muted">v{{ str_pad($doc->versao, 2, '0', STR_PAD_LEFT) }}</td>
                                                <td class="text-muted small">
                                                    {{ $doc->enviado_em?->format('d/m/Y H:i') ?? '—' }}
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge text-bg-{{ $statusCor }}">
                                                        {{ $statusLabel }}
                                                    </span>
                                                </td>
                                                <td class="text-end pe-3">
                                                    <div class="d-flex gap-2 justify-content-end flex-wrap">
                                                        @if ($analise)
                                                            <a href="{{ route('meu-trabalho.analise', $analise) }}"
                                                               class="btn btn-sm btn-outline-primary">
                                                                Ver análise
                                                            </a>
                                                        @else
                                                            <span class="text-muted small">Processando…</span>
                                                        @endif

                                                        @if (! $temTemplateLatex && $bancaPedido && \App\Services\PedidoHomologacaoService::documentoCompativel($bancaPedido, $doc))
                                                            <button type="button" class="btn btn-sm btn-outline-success"
                                                                    data-bs-toggle="modal" data-bs-target="#modal-homologacao-doc-{{ $doc->id }}">
                                                                <i class="bi bi-send-fill me-1"></i>Enviar à homologação
                                                            </button>
                                                            @include('meu-trabalho.partials.modal-envio-homologacao', [
                                                                'idModal' => 'modal-homologacao-doc-'.$doc->id,
                                                                'action' => route('meu-trabalho.enviar-homologacao', $doc),
                                                                'oQue' => 'este documento',
                                                            ])
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

        </div>

        @if ($trabalho)
            @php $dadosPesquisaOk = $trabalho->dadosPesquisa?->preenchido_em !== null; @endphp
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="h6 fw-bold mb-1" style="color:#00303A;">
                            <i class="bi bi-clipboard2-data me-2"></i>Dados da pesquisa
                            @if ($dadosPesquisaOk)
                                <span class="badge bg-success-subtle text-success-emphasis ms-1">Preenchido</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning-emphasis ms-1">Pendente</span>
                            @endif
                        </h2>
                        <p class="text-muted small mb-0">
                            Código, dados e ética (CAAE, Comissão de Ética no Uso de Animais). Obrigatório para enviar o pedido à homologação.
                        </p>
                    </div>
                    <a href="{{ route('meu-trabalho.dados-pesquisa.edit') }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil-fill me-1"></i>{{ $dadosPesquisaOk ? 'Revisar' : 'Preencher' }}
                    </a>
                </div>
            </div>

            @if ($bancaPedido?->tipo === 'defesa')
                @php $espelhoEnviado = \App\Models\EspelhoDiscente::where('banca_id', $bancaPedido->id)->exists(); @endphp
                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h2 class="h6 fw-bold mb-1" style="color:#00303A;">
                                <i class="bi bi-ui-checks me-2"></i>Espelho da avaliação
                                @if ($espelhoEnviado)
                                    <span class="badge bg-success-subtle text-success-emphasis ms-1">Enviado</span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis ms-1">Pendente</span>
                                @endif
                            </h2>
                            <p class="text-muted small mb-0">
                                Suas notas, de 0 a 10, às perguntas que a banca vai avaliar. Obrigatório para enviar a defesa à homologação; privado à CPA.
                            </p>
                        </div>
                        @unless ($espelhoEnviado)
                            <a href="{{ route('meu-trabalho.espelho.form') }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil-fill me-1"></i>Responder
                            </a>
                        @endunless
                    </div>
                </div>
            @endif

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="h6 fw-bold mb-1" style="color:#00303A;">
                            <i class="bi bi-person-vcard me-2"></i>Dados para o Lattes
                        </h2>
                        <p class="text-muted small mb-0">
                            Áreas do CNPq, setor de aplicação, título em inglês e outras informações que os membros da banca usam para registrar a participação no Lattes.
                        </p>
                    </div>
                    <a href="{{ route('meu-trabalho.lattes.edit') }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil-fill me-1"></i>Preencher
                    </a>
                </div>
            </div>
        @endif

        {{-- ── Ações pós-banca ─────────────────────────────────────────────── --}}
        @php $bancaAtiva = $bancas->first(fn($b) => in_array($b->status, ['cadastrada', 'em_avaliacao'])); @endphp
        @if ($bancaAtiva)
        <div class="mt-4">
            <h2 class="h6 fw-bold text-uppercase text-muted mb-3">
                <i class="bi bi-stars me-2"></i>Avalie sua orientação
            </h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="fw-bold mb-1" style="color:#00303A;">
                                <i class="bi bi-person-check-fill me-2"></i>Avaliação da Orientação (IEO)
                            </h6>
                            <p class="text-muted small mb-3">
                                Avalie a orientação recebida. As respostas são confidenciais e agrupadas.
                                Disponível enquanto a banca não for finalizada.
                            </p>
                            <a href="{{ route('orientacao-avaliacao.create', $bancaAtiva) }}"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil-fill me-1"></i>Responder questionário
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="fw-bold mb-1" style="color:#00303A;">
                                <i class="bi bi-lightbulb-fill me-2"></i>Impacto da Dissertação
                            </h6>
                            <p class="text-muted small mb-3">
                                Descreva as dimensões de impacto do seu trabalho (científico, social, ambiental etc.).
                            </p>
                            <a href="{{ route('trabalho-impacto.edit') }}"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil-fill me-1"></i>Preencher impactos
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- ── Modal de carregamento ──────────────────────────────────────── --}}
    <div class="modal fade" id="modalCarregando" tabindex="-1"
         data-bs-backdrop="static" data-bs-keyboard="false"
         aria-labelledby="modalCarregandoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-body text-center py-4 px-4">

                    <div class="loader-con mx-auto mb-3">
                        <div style="--i: 0;" class="pfile"></div>
                        <div style="--i: 1;" class="pfile"></div>
                        <div style="--i: 2;" class="pfile"></div>
                        <div style="--i: 3;" class="pfile"></div>
                        <div style="--i: 4;" class="pfile"></div>
                        <div style="--i: 5;" class="pfile"></div>
                    </div>

                    <p id="loading-msg" class="fw-semibold mb-1" style="color:#00303A; font-size:.95rem;"></p>
                    <p class="text-muted small mb-0">Isso pode levar alguns instantes&hellip;</p>

                </div>
            </div>
        </div>
    </div>

    <style>
        .loader-con {
            position: relative;
            width: 100%;
            height: 70px;
            overflow: hidden;
        }
        .pfile {
            position: absolute;
            bottom: 10px;
            width: 36px;
            height: 44px;
            background: linear-gradient(90deg, #b324db, #ac8dcb);
            border-radius: 4px;
            animation: flyRight 3s ease-in-out infinite;
            animation-delay: calc(var(--i) * 0.6s);
            opacity: 0;
        }
        .pfile::before {
            content: "";
            position: absolute;
            top: 6px; left: 6px;
            width: 24px; height: 4px;
            background: #fff;
            border-radius: 2px;
        }
        .pfile::after {
            content: "";
            position: absolute;
            top: 13px; left: 6px;
            width: 16px; height: 4px;
            background: #fff;
            border-radius: 2px;
        }
        @keyframes flyRight {
            0%   { left: -10%; transform: scale(0);   opacity: 0; }
            50%  { left:  45%; transform: scale(1.2); opacity: 1; }
            100% { left: 100%; transform: scale(0);   opacity: 0; }
        }
    </style>

    <script>
        (function () {
            const form  = document.getElementById('form-envio-documento');
            const msgEl = document.getElementById('loading-msg');
            if (!form || !msgEl) return;

            const mensagens = [
                'Enviando documento…',
                'Extraindo texto do PDF…',
                'Verificando estrutura e elementos…',
                'Avaliando com inteligência artificial…',
                'Gerando relatório de análise…',
            ];

            let idx = 0;

            form.addEventListener('submit', function () {
                msgEl.textContent = mensagens[0];
                bootstrap.Modal.getOrCreateInstance(
                    document.getElementById('modalCarregando')
                ).show();

                setInterval(function () {
                    idx = (idx + 1) % mensagens.length;
                    msgEl.textContent = mensagens[idx];
                }, 3500);
            });
        })();
    </script>
</x-app-layout>
