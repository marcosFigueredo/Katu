{{-- Relatório de verificação das referências ($submissao). Só discente e avaliadores (regra 8): nunca na tela da Secretaria. --}}
@php
    $statusRefs = $submissao->refs_status;
    $refs = $statusRefs === 'concluida' ? $submissao->referencias()->orderBy('id')->get() : collect();
    $contagem = $refs->countBy('status');
    $pct = $submissao->refs_recentes_pct;
@endphp

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent py-3">
        <h3 class="h6 fw-bold mb-0">Verificação das referências</h3>
        <p class="text-muted small mb-0">Conferência automática no Crossref e no OpenAlex; orientativa, não decide nada.</p>
    </div>

    @if ($statusRefs !== 'concluida')
        <div class="card-body small text-muted">
            @if (in_array($statusRefs, ['pendente', 'processando'], true))
                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                Verificação em andamento. Atualize a página em alguns minutos.
            @else
                A verificação das referências não pôde ser concluída.
            @endif
        </div>
    @else
        <div class="card-body">
            <div class="row g-3 text-center mb-3">
                <div class="col-6 col-md-3">
                    <div class="fs-4 fw-bold">{{ (int) $submissao->refs_total }}</div>
                    <div class="small text-muted">referências</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fs-4 fw-bold text-success">{{ $contagem['localizada'] ?? 0 }}</div>
                    <div class="small text-muted">localizadas</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fs-4 fw-bold text-warning">{{ $contagem['nao_localizada'] ?? 0 }}</div>
                    <div class="small text-muted">não localizadas — verificar</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fs-4 fw-bold text-secondary">{{ $contagem['nao_verificada'] ?? 0 }}</div>
                    <div class="small text-muted">não verificadas (falha na consulta)</div>
                </div>
            </div>

            <ul class="small mb-3">
                <li>
                    <strong>Atualidade:</strong>
                    @if ($pct !== null)
                        {{ rtrim(rtrim(number_format((float) $pct, 2, ',', ''), '0'), ',') }}% das {{ (int) $submissao->refs_com_ano }} referências com ano conhecido são dos 2 últimos anos
                        — {{ $submissao->refs_atualizadas ? 'acima' : 'não acima' }} de 70%.
                    @else
                        nenhuma referência com ano conhecido.
                    @endif
                </li>
                <li><strong>Produção do orientador:</strong> {{ (int) $submissao->refs_orientador }} {{ (int) $submissao->refs_orientador === 1 ? 'referência' : 'referências' }}.</li>
            </ul>
        </div>

        @if ($refs->isNotEmpty())
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Referência</th>
                            <th>Situação</th>
                            <th>DOI</th>
                            <th>Ano</th>
                            <th>Observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($refs as $r)
                            @php
                                [$rotulo, $cor] = match ($r->status) {
                                    'localizada'     => ['localizada', 'success'],
                                    'nao_localizada' => ['não localizada — verificar', 'warning'],
                                    default          => ['não verificada — tentar de novo', 'secondary'],
                                };
                            @endphp
                            <tr>
                                <td class="ps-3 text-break" style="max-width:26rem;">
                                    <div class="fw-semibold">{{ $r->chave }}</div>
                                    <div class="text-muted">{{ \Illuminate\Support\Str::limit($r->texto, 220) }}</div>
                                </td>
                                <td><span class="badge text-bg-{{ $cor }}">{{ $rotulo }}</span></td>
                                <td class="text-break">{{ $r->doi_encontrado ?? $r->doi_tex ?? '—' }}</td>
                                <td>{{ $r->anoConsiderado() ?? '—' }}</td>
                                <td>
                                    @if ($r->divergencia_doi)
                                        <div class="text-warning">DOI diferente do .bbl ({{ $r->doi_tex }})</div>
                                    @endif
                                    @if ($r->divergencia_ano)
                                        <div class="text-warning">Ano diferente do .bbl ({{ $r->ano_tex }})</div>
                                    @endif
                                    @if ($r->do_orientador)
                                        <div class="text-primary">Produção do orientador</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
