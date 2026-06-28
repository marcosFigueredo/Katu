<x-app-layout>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-1"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="{{ route('relatorios.index') }}">Relatórios</a></li><li class="breadcrumb-item active">SWARA</li></ol></nav>
    <h1 class="h3 fw-bold mb-3" style="color:#00303A;">Pesos SWARA por Sessão</h1>

    @if ($sessoes->isEmpty())
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Nenhuma sessão SWARA realizada ainda. <a href="{{ route('swara.index') }}">Criar sessão</a>.</div>
    @else

    {{-- Análise 34: Pesos w_j por barema/sessão --}}
    @foreach ($pesoPorSessao as $ps)
    @php $sessao = $ps['sessao']; $itens = $ps['itens']; @endphp
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fw-bold mb-0" style="color:#00303A;"><i class="bi bi-sliders me-2"></i>{{ $sessao->nome ?? 'Sessão #'.$sessao->id }}</h6>
                <div class="text-muted small">Barema: {{ $sessao->barema?->nome ?? '—' }} · {{ $sessao->created_at->format('d/m/Y') }}</div>
            </div>
            <a href="{{ route('swara.show', $sessao) }}" class="btn btn-sm btn-outline-secondary">Ver sessão</a>
        </div>
        <div class="card-body p-0">
            @if ($itens->isEmpty())
                <p class="text-muted small p-3 mb-0">Nenhum julgamento registrado.</p>
            @else
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3 text-center" style="width:50px;">Ordem</th><th>Item</th><th>Seção</th><th class="text-center">s_j</th><th class="text-center">k_j</th><th class="text-center">q_j</th><th class="text-center fw-bold">w_j</th><th style="width:120px;">Peso relativo</th></tr>
                    </thead>
                    <tbody>
                        @php $wMax = $itens->max('w_j') ?: 1; @endphp
                        @foreach ($itens as $item)
                        <tr>
                            <td class="text-center ps-3 text-muted small">{{ $item['ordem'] }}</td>
                            <td class="small fw-semibold">{{ $item['item'] }}</td>
                            <td class="small text-muted">{{ $item['secao'] }}</td>
                            <td class="text-center small">{{ number_format($item['s_j'] ?? 0, 3) }}</td>
                            <td class="text-center small">{{ number_format($item['k_j'] ?? 0, 3) }}</td>
                            <td class="text-center small">{{ number_format($item['q_j'] ?? 0, 3) }}</td>
                            <td class="text-center fw-bold" style="color:#00303A;">{{ number_format($item['w_j'] ?? 0, 4) }}</td>
                            <td>
                                <div class="progress" style="height:8px;">
                                    <div class="progress-bar" style="width:{{ $wMax > 0 ? round(($item['w_j'] ?? 0)/$wMax*100) : 0 }}%; background:#00303A;"></div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="6" class="text-end small fw-semibold ps-3">Soma w_j</td>
                            <td class="text-center fw-bold">{{ number_format($itens->sum('w_j'), 4) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @endif
        </div>
    </div>
    @endforeach

    @endif
</div>
</x-app-layout>
