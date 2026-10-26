<x-app-layout>
<div class="container-fluid">

    <nav aria-label="breadcrumb" class="mb-1"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="{{ route('relatorios.index') }}">Relatórios</a></li><li class="breadcrumb-item active">Perguntas Estratégicas</li></ol></nav>

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Perguntas Estratégicas</h1>
            <p class="text-muted mb-0">Respostas automáticas às perguntas da CAPES e da comissão de avaliação, calculadas a partir dos indicadores do programa.</p>
        </div>
        <a href="{{ route('perguntas-estrategicas.create') }}" class="btn btn-primary fw-semibold">
            <i class="bi bi-plus-lg me-1"></i> Nova pergunta
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($periodos->isEmpty())
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Nenhum período de autoavaliação concluído ainda. As perguntas não podem ser calculadas sem um período de referência. <a href="{{ route('autoavaliacao.index') }}">Ir para Autoavaliação</a>.</div>
    @else
        <form method="GET" class="d-flex align-items-center gap-2 mb-3">
            <label class="form-label fw-semibold mb-0 small">Período de referência:</label>
            <select name="periodo" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                @foreach ($periodos as $p)
                    <option value="{{ $p->id }}" {{ $periodoAtual?->id === $p->id ? 'selected' : '' }}>{{ $p->nome }}</option>
                @endforeach
            </select>
        </form>
    @endif

    @if ($perguntas->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-question-circle fs-1 text-muted mb-3 d-block"></i>
                <p class="text-muted mb-1">Nenhuma pergunta estratégica cadastrada.</p>
                <a href="{{ route('perguntas-estrategicas.create') }}" class="btn btn-primary mt-2">
                    <i class="bi bi-plus-lg me-1"></i> Cadastrar primeira pergunta
                </a>
            </div>
        </div>
    @endif

    @php
        $corStatus = [
            'verde'         => 'success',
            'amarelo'       => 'warning',
            'vermelho'      => 'danger',
            'sem_limiar'    => 'secondary',
            'sem_dado'      => 'secondary',
            'sem_indicador' => 'secondary',
        ];
        $labelStatus = [
            'verde'         => 'Atende',
            'amarelo'       => 'Atende parcialmente',
            'vermelho'      => 'Não atende',
            'sem_limiar'    => 'Sem limiar definido',
            'sem_dado'      => 'Sem dado no período',
            'sem_indicador' => 'Sem indicador vinculado',
        ];
    @endphp

    @foreach ($perguntas as $item)
        @php($pergunta = $item['pergunta'])
        @php($resultado = $item['resultado'])
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                    <div>
                        @if ($pergunta->item_capes)
                            <span class="badge text-bg-dark me-2">Item {{ $pergunta->item_capes }}</span>
                        @endif
                        <span class="badge {{ $pergunta->origem === 'capes' ? 'text-bg-dark' : 'text-bg-light border' }} me-2">
                            {{ $pergunta->origem === 'capes' ? 'CAPES' : 'Comissão' }}
                        </span>
                        <p class="fw-semibold mb-0 mt-2">{{ $pergunta->texto }}</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <span class="badge text-bg-{{ $corStatus[$resultado['status']] ?? 'secondary' }} fs-6 px-3 py-2">
                            {{ $labelStatus[$resultado['status']] ?? '—' }}
                        </span>
                        <a href="{{ route('perguntas-estrategicas.edit', $pergunta) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                    </div>
                </div>

                @if (!empty($resultado['indicadores']))
                    <div class="table-responsive mt-2">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>Indicador</th><th class="text-center">Valor</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($resultado['indicadores'] as $ind)
                                <tr>
                                    <td class="small">{{ $ind['codigo'] }} — {{ $ind['nome'] ?? 'não calculado no período' }}</td>
                                    <td class="text-center">{{ $ind['valor'] !== null ? number_format($ind['valor'], 1) : '—' }}</td>
                                    <td><span class="badge text-bg-{{ $corStatus[$ind['status']] ?? 'secondary' }}">{{ $labelStatus[$ind['status']] ?? '—' }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

</div>
</x-app-layout>
