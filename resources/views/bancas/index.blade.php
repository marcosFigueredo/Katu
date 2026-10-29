<x-app-layout>
    @php
        $nomeDiscente = fn ($banca) => $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user?->name ?? '—';
        $matricula = fn ($banca) => $banca->trabalhoAcademico?->discenteVinculo?->matricula;
        $presidente = fn ($banca) => $banca->trabalhoAcademico?->orientacoes?->firstWhere('tipo', 'orientador')
            ?->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '—';
        $etapa = fn ($banca) => $banca->tipo === 'defesa' ? 'Defesa' : 'Qualificação';
        $avaliadores = fn ($banca) => $banca->membros->where('papel', 'avaliador')->where('ativo', true)
            ->map(fn ($m) => $m->docenteProgramaVinculo?->docenteProfile?->user?->name)->filter();
    @endphp

    <div class="container-fluid">

        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1" style="color: #00303A;">Bancas</h1>
            <p class="text-muted mb-0">
                Bancas solicitadas pelos discentes: analise o pedido, responda os critérios da CPA e homologue ou devolva.
            </p>
        </div>

        @foreach (['success', 'status'] as $chave)
            @if (session($chave))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>{{ session($chave) }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
        @endforeach

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Aguardando homologação --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h2 class="h6 fw-bold mb-0" style="color:#00303A;">
                    <i class="bi bi-hourglass-split me-1"></i>Aguardando homologação
                </h2>
                <span class="badge text-bg-primary">{{ $aguardando->count() }}</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Discente</th>
                            <th>Etapa</th>
                            <th>Presidência e avaliadores</th>
                            <th>Verificações</th>
                            <th class="text-end pe-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($aguardando as $banca)
                            @php
                                $analise = $analises[$banca->id];
                                $itens = $analise['composicao']->map(fn ($i) => $i)->push($analise['template'])->push($analise['baremas'])->push([
                                    'descricao' => 'Critérios da CPA', 'exigido' => '100% Sim',
                                    'encontrado' => $analise['cpa_ok'] ? 'Conformes' : 'Pendente / Não conforme', 'ok' => $analise['cpa_ok'],
                                ]);
                            @endphp
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold">{{ $nomeDiscente($banca) }}</div>
                                    <small class="text-muted">{{ $matricula($banca) }}</small>
                                    @if ((int) $banca->homologacao_tentativa > 1)
                                        <div><span class="badge text-bg-light border">Reenvio — tentativa {{ $banca->homologacao_tentativa }}</span></div>
                                    @endif
                                </td>
                                <td>
                                    {{ $etapa($banca) }}<br>
                                    <small class="text-muted">{{ ucfirst($banca->trabalhoAcademico?->nivel ?? '') }}</small>
                                </td>
                                <td class="small">
                                    <div class="fw-semibold">{{ $presidente($banca) }}</div>
                                    <div class="text-muted">{{ $avaliadores($banca)->implode(', ') ?: 'Sem avaliadores' }}</div>
                                </td>
                                <td class="small">
                                    @foreach ($itens as $item)
                                        <div title="Exigido: {{ $item['exigido'] }} · Encontrado: {{ $item['encontrado'] }}">
                                            <i class="bi bi-{{ $item['ok'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' }} me-1"></i>{{ $item['descricao'] }}
                                        </div>
                                    @endforeach
                                </td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('bancas.homologacao.show', $banca) }}" class="btn btn-sm btn-primary">
                                        <i class="bi bi-clipboard-check me-1"></i>Iniciar homologação
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Nenhuma banca aguardando homologação.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Devolvidas ao discente --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h2 class="h6 fw-bold mb-0" style="color:#00303A;">
                    <i class="bi bi-arrow-return-left me-1"></i>Devolvidas ao discente
                </h2>
                <span class="badge text-bg-warning">{{ $devolvidas->count() }}</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Discente</th>
                            <th>Etapa</th>
                            <th>Motivo</th>
                            <th class="text-end pe-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($devolvidas as $banca)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold">{{ $nomeDiscente($banca) }}</div>
                                    <small class="text-muted">{{ $matricula($banca) }}</small>
                                </td>
                                <td>{{ $etapa($banca) }}</td>
                                <td class="small" style="white-space: pre-line;">{{ \Illuminate\Support\Str::limit($banca->homologacao_observacoes ?? '—', 240) }}
                                    @if ($banca->devolvida_em)
                                        <div class="text-muted">Devolvida em {{ $banca->devolvida_em->format('d/m/Y') }}{{ $banca->devolvidaPor ? ' por '.$banca->devolvidaPor->name : '' }}</div>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('bancas.homologacao.show', $banca) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye me-1"></i>Ver registro
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Nenhuma banca devolvida aguardando ajustes do discente.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Homologadas, em avaliação --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h2 class="h6 fw-bold mb-0" style="color:#00303A;">
                    <i class="bi bi-send-check me-1"></i>Homologadas — em avaliação
                </h2>
                <span class="badge text-bg-success">{{ $homologadas->count() }}</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Discente</th>
                            <th>Etapa</th>
                            <th>Homologação</th>
                            <th class="text-end pe-4">Documentos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($homologadas as $banca)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold">{{ $nomeDiscente($banca) }}</div>
                                    <small class="text-muted">Presidente: {{ $presidente($banca) }}</small>
                                </td>
                                <td>
                                    {{ $etapa($banca) }}<br>
                                    <small class="text-muted">{{ $banca->data_hora?->format('d/m/Y H:i') ?? 'Data a definir' }}</small>
                                </td>
                                <td class="small">
                                    <span class="badge text-bg-success mb-1"><i class="bi bi-check2-all me-1"></i>Homologada e enviada</span>
                                    <div class="text-muted">
                                        {{ $banca->homologadaPor?->name ?? 'Secretaria' }}
                                        @if ($banca->homologada_em) em {{ $banca->homologada_em->format('d/m/Y \à\s H:i') }} @endif
                                        · PDF a {{ $avaliadores($banca)->count() }} avaliador(es)
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex gap-1 justify-content-end flex-wrap">
                                        <a href="{{ route('bancas.homologacao.show', $banca) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i>Ver registro
                                        </a>
                                        <a href="{{ route('avaliacoes.ata', $banca) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-file-earmark-text me-1"></i>Ata
                                        </a>
                                        <a href="{{ route('avaliacoes.folha-aprovacao', $banca) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-file-earmark-pdf me-1"></i>Folha de assinaturas
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Nenhuma banca homologada em avaliação.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Finalizadas --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h2 class="h6 fw-bold mb-0" style="color:#00303A;">
                    <i class="bi bi-flag me-1"></i>Finalizadas
                </h2>
                <span class="badge text-bg-secondary">{{ $finalizadas->count() }}</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Discente</th>
                            <th>Etapa</th>
                            <th>Resultado</th>
                            <th class="text-end pe-4">Documentos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($finalizadas as $banca)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold">{{ $nomeDiscente($banca) }}</div>
                                    <small class="text-muted">Presidente: {{ $presidente($banca) }}</small>
                                </td>
                                <td>
                                    {{ $etapa($banca) }}<br>
                                    <small class="text-muted">{{ $banca->data_hora?->format('d/m/Y') }}</small>
                                </td>
                                <td class="small">
                                    {{ $banca->resultado ? ucfirst(str_replace('_', ' ', $banca->resultado)) : '—' }}
                                    @if ($banca->nota_media !== null)
                                        · nota {{ number_format($banca->nota_media, 2, ',', '.') }}
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('avaliacoes.ata', $banca) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-file-earmark-text me-1"></i>Ata
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Nenhuma banca finalizada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-app-layout>
