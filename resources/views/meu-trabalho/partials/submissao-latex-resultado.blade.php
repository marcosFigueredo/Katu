{{-- Resultado da verificação automática da submissão LaTeX (renderizado também no polling JSON). --}}
@php
    $statusCor = match ($submissao->status) {
        'ok'     => 'success',
        'nao_ok' => 'danger',
        'erro'   => 'secondary',
        default  => 'warning',
    };
    $statusLabel = match ($submissao->status) {
        'ok'     => 'Ok — pode seguir à homologação',
        'nao_ok' => 'Não ok — corrija e envie novamente',
        'erro'   => 'Erro no processamento — envie novamente',
        default  => 'Processando…',
    };
    $itens = $submissao->conformidade ?? [];
    $avisos = $submissao->avisos ?? [];
@endphp

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h2 class="h5 fw-bold text-dark mb-1">Verificação automática</h2>
            <p class="text-muted small mb-0">
                Compilação, elementos vitais e conformidade com o cadastro.
                @unless ($vigente)
                    <span class="badge text-bg-light border ms-1">Substituída por um envio mais recente</span>
                @endunless
            </p>
        </div>
        <span class="badge text-bg-{{ $statusCor }} fs-6 px-3 py-2">
            @if ($submissao->status === 'processando')
                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
            @endif
            {{ $statusLabel }}
        </span>
    </div>
</div>

@if ($submissao->status !== 'processando')
    @if ($itens !== [])
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent py-3">
                <h3 class="h6 fw-bold mb-0">Elementos vitais</h3>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width:2.5rem;"></th>
                            <th>Elemento</th>
                            <th>No .tex</th>
                            <th>No sistema</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($itens as $item)
                            @php
                                [$icone, $cor, $titulo] = match ($item['status']) {
                                    'ok'         => ['✔', 'success', 'Encontrado'],
                                    'divergente' => ['⚠', 'warning', 'Divergente do cadastro'],
                                    'aviso'      => ['⚠', 'secondary', 'Não encontrado (não bloqueia)'],
                                    default      => ['✖', 'danger', 'Não encontrado'],
                                };
                            @endphp
                            <tr>
                                <td class="ps-3 fs-5 text-{{ $cor }}" title="{{ $titulo }}">{{ $icone }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $item['rotulo'] }}</div>
                                    <div class="text-{{ $cor }}">
                                        {{ $titulo }}
                                        @if ($item['bloqueia'])
                                            · impede o "ok"
                                        @endif
                                    </div>
                                </td>
                                <td style="max-width:28rem;">
                                    @if (filled($item['valor_tex'] ?? null))
                                        <div class="text-break">{{ \Illuminate\Support\Str::limit($item['valor_tex'], 400) }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                    @if (! empty($item['diferencas']['somente_tex']))
                                        <div class="text-warning mt-1">Só no .tex: {{ implode('; ', $item['diferencas']['somente_tex']) }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if (filled($item['valor_sistema'] ?? null))
                                        <div class="text-break">{{ $item['valor_sistema'] }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                    @if (! empty($item['diferencas']['somente_sistema']))
                                        <div class="text-warning mt-1">Só no sistema: {{ implode('; ', $item['diferencas']['somente_sistema']) }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($avisos !== [] || filled($submissao->log_resumido))
        <details class="card border-0 shadow-sm mb-4" @if ($submissao->status !== 'ok') open @endif>
            <summary class="card-header bg-transparent py-3 fw-semibold">
                Avisos e log de compilação
                @if ($avisos !== [])
                    <span class="badge text-bg-warning ms-1">{{ count($avisos) }}</span>
                @endif
            </summary>
            <div class="card-body">
                @if ($avisos !== [])
                    <ul class="small mb-3">
                        @foreach ($avisos as $aviso)
                            <li>{{ $aviso }}</li>
                        @endforeach
                    </ul>
                @endif
                @if (filled($submissao->log_resumido))
                    <pre class="small bg-light p-2 mb-0" style="max-height:20rem; overflow:auto; white-space:pre-wrap;">{{ $submissao->log_resumido }}</pre>
                @endif
            </div>
        </details>
    @endif

    @if ($submissao->bbl_path)
        @include('partials.relatorio-referencias', ['submissao' => $submissao])
    @endif

    @if ($submissao->pdf_banca_path || $submissao->pdf_conferencia_path)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                <span class="fw-semibold me-2">Versões em PDF:</span>
                @if ($submissao->pdf_banca_path)
                    <a href="{{ route('meu-trabalho.submissao-latex.pdf', [$submissao, 'banca']) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-file-earmark-pdf me-1"></i>Versão da banca{{ $submissao->numeracao_linhas ? ' (linhas numeradas)' : '' }}
                    </a>
                @endif
                @if ($submissao->pdf_conferencia_path)
                    <a href="{{ route('meu-trabalho.submissao-latex.pdf', [$submissao, 'conferencia']) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-file-earmark-check me-1"></i>Versão de conferência
                    </a>
                @endif
            </div>
        </div>
    @endif

    @php
        $refsEmAndamento = $submissao->bbl_path !== null && in_array($submissao->refs_status, ['pendente', 'processando'], true);
    @endphp

    @if ($podeEnviar)
        @if ($submissao->bbl_path)
            <div class="alert alert-info d-flex align-items-start gap-2 mb-3" role="alert">
                <i class="bi bi-info-circle-fill mt-1"></i>
                <div class="small">
                    <div class="fw-semibold">Informe orientativo sobre as referências concluído</div>
                    @if ($submissao->refs_status === 'concluida')
                        Conferência finalizada ({{ (int) $submissao->refs_total }} {{ (int) $submissao->refs_total === 1 ? 'referência analisada' : 'referências analisadas' }}).
                        Esta verificação é <strong>orientativa e não impede o envio</strong>: revise o relatório acima (e a versão de conferência em PDF, se desejar ajustar alguma citação antes) ou prossiga com o envio à homologação.
                    @else
                        A conferência automática das referências foi encerrada. Como ela tem caráter orientativo e não impede o envio, você já pode encaminhar o pedido à homologação.
                    @endif
                </div>
            </div>
        @endif

        <button type="button" class="btn btn-success fw-semibold"
                data-bs-toggle="modal" data-bs-target="#modal-homologacao-latex-{{ $submissao->id }}">
            <i class="bi bi-send-fill me-1"></i>Enviar à homologação
        </button>
        @include('meu-trabalho.partials.modal-envio-homologacao', [
            'idModal' => 'modal-homologacao-latex-'.$submissao->id,
            'action' => route('meu-trabalho.submissao-latex.enviar-homologacao', $submissao),
            'oQue' => 'este trabalho',
        ])
    @elseif ($vigente && $submissao->status === 'ok' && $refsEmAndamento)
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-0" role="alert">
            <span class="spinner-border spinner-border-sm flex-shrink-0" role="status"></span>
            <div class="small">
                <strong>Aguardando conclusão da conferência das referências…</strong>
                O envio à homologação será liberado automaticamente assim que a conferência completa das referências e o informe orientativo ao estudante forem concluídos.
            </div>
        </div>
    @elseif ($vigente && in_array($submissao->status, ['nao_ok', 'erro'], true))
        <a href="{{ route('meu-trabalho.index') }}" class="btn btn-outline-primary">
            <i class="bi bi-arrow-repeat me-1"></i>Corrigir e enviar novamente
        </a>
    @endif
@endif
