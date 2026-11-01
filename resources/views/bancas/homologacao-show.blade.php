<x-app-layout>
    @php
        $trabalho  = $banca->trabalhoAcademico;
        $discente  = $trabalho?->discenteVinculo?->discenteProfile?->user;
        $orientador = $trabalho?->orientacoes
            ?->where('tipo', 'orientador')
            ?->where('ativo', true)
            ?->first()
            ?->docenteProgramaVinculo?->docenteProfile?->user;
        $avaliadores = $banca->membros->where('papel', 'avaliador')->where('ativo', true);
        $docHomologacao = $banca->documentoHomologacao;
        $submissao = $docHomologacao?->submissaoLatex;
        $tpl = $analise['template'];
        $somenteLeitura = $banca->homologacao_status === 'homologada';
    @endphp

    <div class="container-fluid" style="max-width: 1050px;">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <a href="{{ route('bancas.index') }}" class="text-muted text-decoration-none small">
                <i class="bi bi-arrow-left me-1"></i>Voltar para Bancas
            </a>
            <span class="badge text-bg-{{ match($banca->homologacao_status) {
                'homologada' => 'success',
                'devolvida'  => 'warning',
                'aguardando' => 'primary',
                default      => 'secondary',
            } }} fs-6">
                {{ ucfirst($banca->homologacao_status) }}
            </span>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1" style="color:#00303A;">
                    Análise de Homologação — {{ ucfirst($banca->tipo) }}
                </h1>
                <p class="text-muted mb-0">
                    Tentativa #{{ $banca->homologacao_tentativa }} · {{ $discente?->name ?? '—' }}
                </p>
            </div>
            @if ($docHomologacao)
                <div class="d-flex gap-2">
                    @if ($submissao && $submissao->pdf_banca_path)
                        <a href="{{ route('meu-trabalho.submissao-latex.pdf', [$submissao, 'banca']) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Abrir PDF compilado (v{{ $docHomologacao->versao }})
                        </a>
                    @else
                        <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('meu-trabalho.download', now()->addHours(2), ['documento' => $docHomologacao->id]) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Baixar PDF do trabalho (v{{ $docHomologacao->versao }})
                        </a>
                    @endif
                </div>
            @endif
        </div>

        {{-- Registro de envio quando homologada --}}
        @if ($banca->homologacao_status === 'homologada')
            <div class="alert alert-success border-0 shadow-sm mb-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h2 class="h6 fw-bold mb-1">
                            <i class="bi bi-send-check-fill me-1"></i>Registro de Homologação e Envio Realizado
                        </h2>
                        <p class="mb-1 small">
                            <strong>Homologada por:</strong> {{ $banca->homologadaPor?->name ?? 'Secretaria' }}
                            @if ($banca->homologada_em)
                                em {{ $banca->homologada_em->format('d/m/Y \à\s H:i') }}
                            @endif
                        </p>
                        <p class="mb-0 small">
                            <strong>Envio registrado em:</strong>
                            {{ ($banca->enviada_banca_em ?? $banca->homologada_em)?->format('d/m/Y \à\s H:i') ?? '—' }}
                            — PDF enviado aos {{ $avaliadores->count() }} membros da banca e Folha de Assinaturas (template LaTeX) + Ata de Assinatura enviadas ao orientador ({{ $orientador?->name ?? '—' }}).
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('avaliacoes.ata', $banca) }}" target="_blank" class="btn btn-sm btn-light border">
                            <i class="bi bi-file-earmark-text me-1"></i>Ata de assinatura
                        </a>
                        <a href="{{ route('avaliacoes.folha-aprovacao', $banca) }}" class="btn btn-sm btn-light border">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Folha de assinaturas (LaTeX)
                        </a>
                    </div>
                </div>
            </div>
        @endif

        {{-- Dados do pedido e da banca --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Discente</div>
                        <div class="fw-semibold">{{ $discente?->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Orientador(a) — Presidente da banca</div>
                        <div class="fw-semibold">{{ $orientador?->name ?? '—' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Título do trabalho</div>
                        <div class="fw-semibold">{{ $trabalho?->titulo ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Etapa / Nível</div>
                        <div class="fw-semibold">{{ ucfirst($banca->tipo) }} ({{ ucfirst($trabalho?->nivel ?? '') }})</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Data e horário</div>
                        <div class="fw-semibold">{{ $banca->data_hora?->format('d/m/Y \à\s H:i') ?? 'A definir' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Local / Link</div>
                        <div class="fw-semibold">{{ $banca->local ?: ($banca->link_remoto ?: 'A definir') }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small mb-1">Membros avaliadores indicados</div>
                        <ul class="list-unstyled mb-0 small">
                            @forelse ($avaliadores as $membro)
                                @php
                                    $vinculoDoc = $membro->docenteProgramaVinculo;
                                    $docUser    = $vinculoDoc?->docenteProfile?->user;
                                    $ehExterno  = (bool) $vinculoDoc?->externo;
                                @endphp
                                <li class="mb-1">
                                    <i class="bi bi-person-check me-1 text-secondary"></i>
                                    <strong>{{ $docUser?->name ?? '—' }}</strong>
                                    <span class="badge text-bg-{{ $ehExterno ? 'info' : 'secondary' }} ms-1">
                                        {{ $ehExterno ? 'Externo' : 'Interno' }}
                                    </span>
                                    @if ($docUser?->email)
                                        <span class="text-muted ms-1">({{ $docUser->email }})</span>
                                    @endif
                                </li>
                            @empty
                                <li class="text-muted">Nenhum avaliador cadastrado.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- 1 & 2. Verificações automáticas: Composição da banca + Uso do template --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-bold mb-0" style="color:#00303A;">
                    <i class="bi bi-robot me-1"></i>1 e 2. Verificações automáticas (Composição da banca e Uso do template)
                </h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item verificado automaticamente</th>
                                <th>Exigido</th>
                                <th>Encontrado</th>
                                <th class="text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($analise['composicao'] as $item)
                                <tr>
                                    <td>{{ $item['descricao'] }}</td>
                                    <td>{{ $item['exigido'] }}</td>
                                    <td>{{ $item['encontrado'] }}</td>
                                    <td class="text-end">
                                        @if ($item['ok'])
                                            <span class="badge text-bg-success"><i class="bi bi-check-lg me-1"></i>Conforme</span>
                                        @else
                                            <span class="badge text-bg-danger"><i class="bi bi-x-lg me-1"></i>Pendente</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            <tr>
                                <td>{{ $analise['baremas']['descricao'] }}</td>
                                <td>{{ $analise['baremas']['exigido'] }}</td>
                                <td>{{ $analise['baremas']['encontrado'] }}</td>
                                <td class="text-end">
                                    @if ($analise['baremas_ok'])
                                        <span class="badge text-bg-success"><i class="bi bi-check-lg me-1"></i>Conforme</span>
                                    @else
                                        <span class="badge text-bg-danger"><i class="bi bi-x-lg me-1"></i>Pendente</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>{{ $tpl['descricao'] }}</td>
                                <td>{{ $tpl['exigido'] }}</td>
                                <td>{{ $tpl['encontrado'] }}</td>
                                <td class="text-end">
                                    @if ($tpl['ok'])
                                        <span class="badge text-bg-success"><i class="bi bi-check-lg me-1"></i>Conforme</span>
                                    @else
                                        <span class="badge text-bg-danger"><i class="bi bi-x-lg me-1"></i>Pendente</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if ($submissao)
                    @php
                        $checklist = $submissao->conformidade['checklist'] ?? [];
                        $divergencias = $submissao->conformidade['divergencias'] ?? [];
                    @endphp
                    <hr class="my-3">
                    <h3 class="h6 fw-semibold mb-2">Detalhamento automático do template LaTeX</h3>
                    @if (! empty($checklist))
                        <div class="row g-2 mb-2">
                            @foreach ($checklist as $campo => $ok)
                                <div class="col-sm-6 col-md-4 small">
                                    <i class="bi bi-{{ $ok ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' }} me-1"></i>
                                    <code>{{ $campo }}</code>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if (! empty($divergencias))
                        <div class="alert alert-warning small mb-0 mt-2">
                            <strong>Divergências no template:</strong>
                            <ul class="mb-0 mt-1">
                                @foreach ($divergencias as $div)
                                    <li>{{ $div }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        @include('bancas._conferencia-estudante')
        @include('avaliacoes.partials.informacoes-estudante')

        {{-- 3. Critérios da CPA (Sim / Não) --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h2 class="h6 fw-bold mb-0" style="color:#00303A;">
                    <i class="bi bi-list-check me-1"></i>3. Validação das conformidades dos critérios da CPA
                </h2>
                @if ($analise['cpa_ok'])
                    <span class="badge text-bg-success">100% Sim</span>
                @else
                    <span class="badge text-bg-warning">Validação pendente / Não conforme</span>
                @endif
            </div>
            <div class="card-body">
                @if ($respostas->isEmpty())
                    <p class="text-muted mb-0">Nenhuma pergunta da CPA ativa para esta etapa e nível.</p>
                @else
                    <form method="POST" action="{{ route('bancas.homologacao.responder', $banca) }}">
                        @csrf
                        <div class="table-responsive">
                            <table class="table align-middle mb-3">
                                <thead class="table-light">
                                    <tr>
                                        <th>Critério CPA</th>
                                        <th style="width: 220px;" class="text-end">Conformidade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($respostas as $item)
                                        @php
                                            $criterio = $item['criterio'];
                                            $resp     = $item['resposta'];
                                            $valorAtual = $resp === null ? null : (bool) $resp->resposta;
                                        @endphp
                                        <tr>
                                            <td>{{ $criterio->pergunta }}</td>
                                            <td class="text-end">
                                                <div class="btn-group" role="group">
                                                    <input type="radio"
                                                           class="btn-check"
                                                           name="respostas[{{ $criterio->id }}]"
                                                           id="cpa_{{ $criterio->id }}_sim"
                                                           value="1"
                                                           @checked($valorAtual === true)
                                                           @disabled($somenteLeitura)>
                                                    <label class="btn btn-sm btn-outline-success" for="cpa_{{ $criterio->id }}_sim">
                                                        Sim
                                                    </label>

                                                    <input type="radio"
                                                           class="btn-check"
                                                           name="respostas[{{ $criterio->id }}]"
                                                           id="cpa_{{ $criterio->id }}_nao"
                                                           value="0"
                                                           @checked($valorAtual === false)
                                                           @disabled($somenteLeitura)>
                                                    <label class="btn btn-sm btn-outline-danger" for="cpa_{{ $criterio->id }}_nao">
                                                        Não
                                                    </label>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if (! $somenteLeitura)
                            <div class="text-end">
                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-save me-1"></i>Salvar respostas da CPA
                                </button>
                            </div>
                        @endif
                    </form>
                @endif
            </div>
        </div>

        {{-- 4. Decisão da Secretaria: Homologar ou Devolver --}}
        @if (! $somenteLeitura)
            <div class="row g-4 mb-5">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex flex-column justify-content-between">
                            <div>
                                <h2 class="h6 fw-bold text-success mb-2">
                                    <i class="bi bi-check2-circle me-1"></i>4. Homologar e enviar documentos
                                </h2>
                                <p class="text-muted small mb-3">
                                    Ao homologar:
                                </p>
                                <ul class="small text-muted mb-3">
                                    <li>O PDF do trabalho é enviado por e-mail a todos os membros da banca;</li>
                                    <li>O orientador recebe por e-mail a <strong>Folha de Assinaturas</strong> (conforme o template LaTeX) e a <strong>Ata de Assinatura</strong>;</li>
                                    <li>O registro do envio fica arquivado na Secretaria.</li>
                                </ul>

                                @if (! $analise['pode'])
                                    <div class="alert alert-warning small">
                                        <strong>Homologação bloqueada até que:</strong>
                                        <ul class="mb-0 mt-1">
                                            @if (! $analise['composicao_ok'])
                                                <li>Todas as regras de composição da banca (mínimo 1 interno e 1 externo e ORCID) estejam cumpridas;</li>
                                            @endif
                                            @if (! $analise['template_ok'])
                                                <li>O trabalho submetido esteja em conformidade com o template do programa;</li>
                                            @endif
                                            @if (! $analise['baremas_ok'])
                                                <li>A Secretaria cadastre e ative os baremas da etapa: {{ $analise['baremas']['encontrado'] }}</li>
                                            @endif
                                            @if (! $analise['atividades_ok'])
                                                <li>As atividades formativas exigidas estejam conferidas e confirmadas;</li>
                                            @endif
                                            @if (! $analise['publicacoes_ok'])
                                                <li>As publicações estejam conferidas e atinjam o mínimo do curso;</li>
                                            @endif
                                            @if (! $analise['cpa_ok'])
                                                <li>Todos os critérios da CPA estejam respondidos com <strong>Sim</strong>.</li>
                                            @endif
                                        </ul>
                                    </div>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('bancas.homologar', $banca) }}">
                                @csrf
                                <button type="submit" class="btn btn-success w-100 fw-semibold" @disabled(! $analise['pode'])>
                                    <i class="bi bi-send-check me-1"></i>Homologar e enviar PDF à banca
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex flex-column justify-content-between">
                            <form method="POST" action="{{ route('bancas.devolver', $banca) }}">
                                @csrf
                                <h2 class="h6 fw-bold text-warning mb-2">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Devolver pedido ao discente
                                </h2>
                                <p class="text-muted small mb-2">
                                    Caso haja pendência na composição, no template ou em algum critério da CPA, informe as observações abaixo para que o discente corrija e reenvie.
                                </p>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Observações / Motivo da devolução</label>
                                    <textarea name="observacoes" rows="3" class="form-control form-control-sm" placeholder="Descreva os ajustes necessários..." required>{{ old('observacoes', $banca->homologacao_observacoes) }}</textarea>
                                </div>
                                <button type="submit" class="btn btn-warning w-100 fw-semibold">
                                    <i class="bi bi-reply me-1"></i>Devolver para ajustes
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
