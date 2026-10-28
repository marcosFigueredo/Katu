<x-app-layout>
    <div class="container-fluid">
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

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Homologação de bancas</h1>
                <p class="text-muted mb-0">
                    Verifique automaticamente a composição da banca (mínimo 1 interno e 1 externo), o uso do template e valide os critérios da CPA antes de homologar e enviar o PDF aos membros.
                </p>
            </div>
            <a href="{{ route('bancas.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Voltar às bancas
            </a>
        </div>

        {{-- Fila de pedidos aguardando homologação --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-bold mb-0" style="color:#00303A;">
                    <i class="bi bi-hourglass-split me-1"></i>Pedidos aguardando homologação
                </h2>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Discente</th>
                            <th>Etapa</th>
                            <th>Status</th>
                            <th>Verificações automáticas e CPA</th>
                            <th class="text-end pe-3">Decisão</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bancas as $banca)
                            @php
                                $analise = $analises[$banca->id];
                                $tpl = $analise['template'];
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <strong>{{ $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user?->name ?? '—' }}</strong><br>
                                    <small class="text-muted">{{ $banca->trabalhoAcademico?->titulo ?? 'Trabalho sem título' }}</small>
                                </td>
                                <td>
                                    {{ ucfirst($banca->tipo) }}<br>
                                    <small class="text-muted">{{ ucfirst($banca->trabalhoAcademico?->nivel ?? '') }}</small>
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ $banca->homologacao_status === 'devolvida' ? 'warning' : 'secondary' }}">
                                        {{ ucfirst($banca->homologacao_status) }}
                                    </span>
                                    @if($banca->homologacao_observacoes)
                                        <div class="small text-muted mt-1">{{ $banca->homologacao_observacoes }}</div>
                                    @endif
                                </td>
                                <td>
                                    <table class="table table-sm small mt-2 mb-0">
                                        <thead>
                                            <tr>
                                                <th>Verificação</th>
                                                <th>Exigido</th>
                                                <th>Encontrado</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($verificacoes[$banca->id] as $item)
                                                <tr>
                                                    <td>{{ $item['descricao'] }}</td>
                                                    <td>{{ $item['exigido'] }}</td>
                                                    <td>{{ $item['encontrado'] }}</td>
                                                    <td>
                                                        <i class="bi bi-{{ $item['ok'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' }}"></i>
                                                    </td>
                                                </tr>
                                            @endforeach
                                            <tr>
                                                <td>{{ $tpl['descricao'] }}</td>
                                                <td>{{ $tpl['exigido'] }}</td>
                                                <td>{{ $tpl['encontrado'] }}</td>
                                                <td>
                                                    <i class="bi bi-{{ $tpl['ok'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' }}"></i>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Critérios da CPA</td>
                                                <td>100% Sim</td>
                                                <td>{{ $analise['cpa_ok'] ? 'Conformes' : 'Pendente / Não conforme' }}</td>
                                                <td>
                                                    <i class="bi bi-{{ $analise['cpa_ok'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' }}"></i>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('bancas.homologacao.show', $banca) }}" class="btn btn-sm btn-outline-primary mb-2 w-100">
                                        <i class="bi bi-clipboard-check me-1"></i>Analisar / CPA
                                    </a>
                                    <form method="POST" action="{{ route('bancas.homologar', $banca) }}" class="mb-2">
                                        @csrf
                                        <button class="btn btn-sm btn-success w-100" @disabled(! $analise['pode'])>
                                            Homologar e enviar PDF
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('bancas.devolver', $banca) }}">
                                        @csrf
                                        <input class="form-control form-control-sm mb-1" name="observacoes" placeholder="Motivo da devolução" required>
                                        <button class="btn btn-sm btn-warning w-100">Devolver</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    Nenhuma banca aguardando homologação.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Registro de homologações e envios realizados pela Secretaria --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-bold mb-0" style="color:#00303A;">
                    <i class="bi bi-send-check me-1"></i>Registro de homologações e envios realizados
                </h2>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Discente</th>
                            <th>Etapa</th>
                            <th>Homologada por</th>
                            <th>Registro de envio (Banca e Orientador)</th>
                            <th class="text-end pe-3">Documentos e Ficha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($homologadas as $homologada)
                            @php
                                $qtdAvaliadores = $homologada->membros->where('papel', 'avaliador')->where('ativo', true)->count();
                                $orientadorNome = $homologada->trabalhoAcademico?->orientacoes
                                    ?->where('tipo', 'orientador')
                                    ?->where('ativo', true)
                                    ?->first()
                                    ?->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '—';
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <strong>{{ $homologada->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user?->name ?? '—' }}</strong><br>
                                    <small class="text-muted">{{ $homologada->trabalhoAcademico?->titulo ?? 'Trabalho sem título' }}</small>
                                </td>
                                <td>
                                    {{ ucfirst($homologada->tipo) }}<br>
                                    <small class="text-muted">
                                        {{ $homologada->data_hora?->format('d/m/Y H:i') ?? 'Data a definir' }}
                                    </small>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $homologada->homologadaPor?->name ?? 'Secretaria' }}</div>
                                    <small class="text-muted">
                                        {{ $homologada->homologada_em?->format('d/m/Y \à\s H:i') ?? '—' }}
                                    </small>
                                </td>
                                <td>
                                    <span class="badge text-bg-success mb-1">
                                        <i class="bi bi-check2-all me-1"></i>Homologada e enviada
                                    </span>
                                    <div class="small text-muted">
                                        PDF enviado a {{ $qtdAvaliadores }} membro(s) da banca e Folha de Assinaturas + Ata enviadas a {{ $orientadorNome }}
                                        @if ($homologada->enviada_banca_em)
                                            em {{ $homologada->enviada_banca_em->format('d/m/Y \à\s H:i') }}
                                        @elseif ($homologada->homologada_em)
                                            em {{ $homologada->homologada_em->format('d/m/Y \à\s H:i') }}
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex gap-1 justify-content-end flex-wrap">
                                        <a href="{{ route('bancas.homologacao.show', $homologada) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i>Ver registro
                                        </a>
                                        <a href="{{ route('avaliacoes.ata', $homologada) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-file-earmark-text me-1"></i>Ata
                                        </a>
                                        <a href="{{ route('avaliacoes.folha-aprovacao', $homologada) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-file-earmark-pdf me-1"></i>Folha de assinaturas
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Nenhuma homologação registrada ainda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
