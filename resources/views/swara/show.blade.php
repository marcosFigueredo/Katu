<x-app-layout>
    <div class="container-fluid">

        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('baremas.index') }}">Baremas</a></li>
                <li class="breadcrumb-item"><a href="{{ route('swara.index', $barema) }}">SWARA — {{ $barema->nome }}</a></li>
                <li class="breadcrumb-item active">{{ $sessao->nome }}</li>
            </ol>
        </nav>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row g-4">

            {{-- Info geral --}}
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3" style="color:#00303A;">Sessão</h6>
                        <dl class="row mb-0 small">
                            <dt class="col-5 text-muted">Nome</dt>
                            <dd class="col-7">{{ $sessao->nome }}</dd>
                            <dt class="col-5 text-muted">Barema</dt>
                            <dd class="col-7">{{ $barema->nome }}</dd>
                            <dt class="col-5 text-muted">Aprovado por</dt>
                            <dd class="col-7">{{ $sessao->aprovado_por ?? '—' }}</dd>
                            <dt class="col-5 text-muted">Criada em</dt>
                            <dd class="col-7">{{ $sessao->created_at->format('d/m/Y H:i') }}</dd>
                            <dt class="col-5 text-muted">Aplicada em</dt>
                            <dd class="col-7">
                                @if ($sessao->aplicado_em)
                                    <span class="text-success fw-semibold">{{ $sessao->aplicado_em->format('d/m/Y H:i') }}</span>
                                @else
                                    <span class="text-muted">Não aplicada</span>
                                @endif
                            </dd>
                        </dl>

                        @if ($sessao->descricao)
                            <hr>
                            <p class="small text-muted mb-0">{{ $sessao->descricao }}</p>
                        @endif

                        @if (!$sessao->foiAplicada())
                            <hr>
                            <form method="POST" action="{{ route('swara.aplicar', [$barema, $sessao]) }}"
                                  onsubmit="return confirm('Isso irá sobrescrever os pesos atuais dos itens do barema. Confirmar?')">
                                @csrf
                                <button type="submit" class="btn btn-success w-100 fw-semibold">
                                    <i class="bi bi-check2-circle me-1"></i> Aplicar pesos ao barema
                                </button>
                            </form>
                        @else
                            <div class="alert alert-success py-2 px-3 mb-0 mt-3 small">
                                <i class="bi bi-check-circle-fill me-1"></i>
                                Pesos aplicados ao barema em {{ $sessao->aplicado_em->format('d/m/Y') }}.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Tabela de julgamentos --}}
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0" style="color:#00303A;">
                            <i class="bi bi-table me-2"></i>Matriz de Pesos SWARA
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4" style="width:50px">j</th>
                                        <th>Critério</th>
                                        <th class="text-center">s_j</th>
                                        <th class="text-center">k_j</th>
                                        <th class="text-center">q_j</th>
                                        <th class="text-center pe-4">w_j (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sessao->julgamentos as $j)
                                        <tr>
                                            <td class="ps-4 text-muted">{{ $j->ordem }}</td>
                                            <td class="fw-semibold">{{ $j->baremaItem->nome }}</td>
                                            <td class="text-center">{{ $j->ordem === 1 ? '—' : number_format($j->s_j, 3) }}</td>
                                            <td class="text-center">{{ number_format($j->k_j, 3) }}</td>
                                            <td class="text-center">{{ number_format($j->q_j, 4) }}</td>
                                            <td class="text-center pe-4">
                                                <span class="fw-bold">{{ number_format($j->w_j * 100, 2) }}%</span>
                                                <div class="progress mt-1" style="height:4px">
                                                    <div class="progress-bar bg-primary" style="width:{{ round($j->w_j * 100, 1) }}%"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="5" class="ps-4 fw-semibold text-end pe-3">Total w_j</td>
                                        <td class="text-center pe-4 fw-bold">
                                            {{ number_format($sessao->julgamentos->sum('w_j') * 100, 2) }}%
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
