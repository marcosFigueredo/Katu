<x-app-layout>
    <div class="container-fluid">

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1" style="color: #00303A;">Baremas</h1>
                <p class="text-muted mb-0">
                    Instrumentos de avaliação das bancas examinadoras.
                </p>
            </div>
            <a href="{{ route('baremas.create') }}" class="btn btn-primary fw-semibold">
                <i class="bi bi-plus-lg me-1"></i> Novo barema
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($baremas->isEmpty())
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-clipboard2-check-fill fs-1 text-muted mb-3 d-block"></i>
                    <p class="text-muted mb-1">Nenhum barema cadastrado ainda.</p>
                    <a href="{{ route('baremas.create') }}" class="btn btn-primary mt-2">
                        <i class="bi bi-plus-lg me-1"></i> Criar primeiro barema
                    </a>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Nome</th>
                                    <th>Tipo</th>
                                    <th>Nível</th>
                                    <th>Versão</th>
                                    <th class="text-center">Seções / Itens</th>
                                    <th class="text-center">Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($baremas as $barema)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-semibold text-dark">{{ $barema->nome }}</div>
                                            @if ($barema->descricao)
                                                <div class="text-muted small text-truncate" style="max-width:280px;">
                                                    {{ $barema->descricao }}
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $tipoLabel = match($barema->tipo) {
                                                    'qualificacao'              => ['label' => 'Qualificação',              'color' => 'info'],
                                                    'defesa'                    => ['label' => 'Defesa',                    'color' => 'primary'],
                                                    'qualificacao_confidencial' => ['label' => 'Qualif. Confidencial',      'color' => 'warning'],
                                                    'defesa_confidencial'       => ['label' => 'Defesa Confidencial',       'color' => 'danger'],
                                                    'autoavaliacao_orientador'  => ['label' => 'Autoavaliação do orientador', 'color' => 'secondary'],
                                                };
                                            @endphp
                                            <span class="badge text-bg-{{ $tipoLabel['color'] }} bg-opacity-75">
                                                {{ $tipoLabel['label'] }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ match($barema->nivel) {
                                                'mestrado'  => 'Mestrado',
                                                'doutorado' => 'Doutorado',
                                                'ambos'     => 'Mestrado e Doutorado',
                                            } }}
                                        </td>
                                        <td>
                                            <code class="text-dark">{{ $barema->versao }}</code>
                                        </td>
                                        <td class="text-center">
                                            @php
                                                $totalSecoes = $barema->secoes->count();
                                                $totalItens  = $barema->secoes->sum(fn ($s) => $s->itens->count());
                                            @endphp
                                            <span class="badge text-bg-light border">
                                                {{ $totalSecoes }} seç. / {{ $totalItens }} it.
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if ($barema->ativo)
                                                <span class="badge text-bg-success">Ativo</span>
                                            @else
                                                <span class="badge text-bg-secondary">Inativo</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="{{ route('baremas.edit', $barema) }}"
                                               class="btn btn-sm btn-outline-primary me-1">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalExcluir"
                                                    data-action="{{ route('baremas.destroy', $barema) }}"
                                                    data-nome="{{ $barema->nome }}">
                                                <i class="bi bi-trash3-fill"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

    </div>

    {{-- Modal de confirmação de exclusão --}}
    <div class="modal fade" id="modalExcluir" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title fw-bold text-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Excluir barema
                    </h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-1">Tem certeza que deseja excluir o barema:</p>
                    <p class="fw-semibold mb-0" id="excluir-nome"></p>
                    <p class="text-danger small mt-2 mb-0">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        Todas as seções e itens serão removidos.
                    </p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <form method="POST" id="form-excluir">
                        @csrf @method('DELETE')
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger btn-sm ms-2">Excluir</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('modalExcluir').addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('excluir-nome').textContent = btn.dataset.nome;
            document.getElementById('form-excluir').action = btn.dataset.action;
        });
    </script>
</x-app-layout>
