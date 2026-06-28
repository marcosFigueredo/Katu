<x-app-layout>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-1"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="{{ route('relatorios.index') }}">Relatórios</a></li><li class="breadcrumb-item active">Conformidade Documental</li></ol></nav>
    <h1 class="h3 fw-bold mb-3" style="color:#00303A;">Conformidade Documental</h1>

    @include('relatorios._filtro')

    @if ($analises->isEmpty())
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Nenhuma análise de documento no período selecionado.</div>
    @else

    {{-- Análise 27: Status geral --}}
    <div class="row g-3 mb-4">
        @php
        $corStatus = ['conforme'=>'success','pendente'=>'warning','reprovado'=>'danger','em_analise'=>'info'];
        $labelStatus = ['conforme'=>'Conforme','pendente'=>'Pendente','reprovado'=>'Reprovado','em_analise'=>'Em análise'];
        @endphp
        <div class="col-sm-3 text-center"><div class="card border-0 shadow-sm p-3">
            <div class="fs-2 fw-bold" style="color:#00303A;">{{ $analises->count() }}</div>
            <div class="text-muted small">Documentos analisados</div>
        </div></div>
        <div class="col-sm-3 text-center"><div class="card border-0 shadow-sm p-3">
            <div class="fs-2 fw-bold {{ ($mediaConformidade??0) >= 80 ? 'text-success' : (($mediaConformidade??0) >= 60 ? 'text-warning' : 'text-danger') }}">{{ $mediaConformidade ?? '—' }}%</div>
            <div class="text-muted small">Conformidade média</div>
            <div class="text-muted" style="font-size:.72rem;">Meta: 80%</div>
        </div></div>
        @foreach ($statusGeral as $status => $n)
        <div class="col-sm-3 text-center"><div class="card border-0 shadow-sm p-3">
            <div class="fs-2 fw-bold text-{{ $corStatus[$status] ?? 'secondary' }}">{{ $n }}</div>
            <div class="text-muted small">{{ $labelStatus[$status] ?? $status }}</div>
        </div></div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        {{-- Itens com mais pendências --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-exclamation-triangle-fill me-2"></i>Itens com Mais Pendências/Atenção</h6>
                </div>
                <div class="card-body p-0">
                    @if ($itensPendentes->isEmpty())
                        <p class="text-muted small p-3 mb-0">Nenhuma pendência no período.</p>
                    @else
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th class="ps-3">Item de conformidade</th><th class="text-center">Status</th><th class="text-center">Ocorrências</th></tr></thead>
                        <tbody>
                            @foreach ($itensPendentes as $item)
                            <tr>
                                <td class="ps-3 small fw-semibold">{{ $item->nome_item }}</td>
                                <td class="text-center"><span class="badge text-bg-{{ $item->status === 'pendente' ? 'warning' : 'info' }}">{{ $item->status }}</span></td>
                                <td class="text-center">{{ $item->total }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- Análise 28: LaTeX e PDF --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-file-earmark-pdf me-2"></i>Qualidade Técnica dos PDFs</h6>
                </div>
                <div class="card-body">
                    @php $totL = max($latexTotal, 1); @endphp
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small fw-semibold">Usam LaTeX</span>
                        <span class="badge text-bg-success">{{ $latexUsando }} / {{ $latexTotal }} ({{ round($latexUsando/$totL*100) }}%)</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small fw-semibold">PDF não pesquisável</span>
                        <span class="badge text-bg-{{ $pdfNaoPesquisavel > 0 ? 'danger' : 'success' }}">{{ $pdfNaoPesquisavel }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="small fw-semibold">PDF protegido</span>
                        <span class="badge text-bg-{{ $pdfProtegido > 0 ? 'warning' : 'success' }}">{{ $pdfProtegido }}</span>
                    </div>
                    @if ($motoresTex->isNotEmpty())
                    <h6 class="small fw-bold text-muted mt-3 mb-2">Motores TeX detectados</h6>
                    @foreach ($motoresTex as $motor => $n)
                    <div class="d-flex justify-content-between align-items-center mb-1 small">
                        <span>{{ $motor }}</span>
                        <span class="fw-semibold">{{ $n }}</span>
                    </div>
                    @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>

    @endif
</div>
</x-app-layout>
