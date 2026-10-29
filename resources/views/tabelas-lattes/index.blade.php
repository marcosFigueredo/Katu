<x-app-layout>
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Áreas CNPq e setores CNAE</h1>
                <p class="text-muted mb-0">Listas usadas pelos estudantes nos dados para o Lattes. Valem para todos os programas.</p>
            </div>
            <a href="{{ route('tabelas-lattes.create', ['lista' => $lista]) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Incluir {{ $lista === 'cnpq' ? 'área' : 'setor' }}
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <ul class="nav nav-pills mb-3">
            <li class="nav-item">
                <a class="nav-link {{ $lista === 'cnpq' ? 'active' : '' }}" href="{{ route('tabelas-lattes.index', ['lista' => 'cnpq']) }}">Áreas do CNPq</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $lista === 'cnae' ? 'active' : '' }}" href="{{ route('tabelas-lattes.index', ['lista' => 'cnae']) }}">Setores CNAE</a>
            </li>
        </ul>

        <form method="GET" action="{{ route('tabelas-lattes.index') }}" class="row g-2 mb-3">
            <input type="hidden" name="lista" value="{{ $lista }}">
            <div class="col-md-6">
                <input type="search" name="busca" value="{{ $busca }}" class="form-control" placeholder="Buscar por nome ou código">
            </div>
            <div class="col-auto"><button class="btn btn-outline-secondary">Buscar</button></div>
        </form>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Código</th>
                            <th>{{ $lista === 'cnpq' ? 'Área' : 'Setor' }}</th>
                            <th>{{ $lista === 'cnpq' ? 'Nível' : 'Seção' }}</th>
                            <th>Situação</th>
                            <th class="text-end pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($itens as $item)
                            <tr class="{{ $item->ativo ? '' : 'text-muted' }}">
                                <td class="ps-3"><code>{{ $item->codigo }}</code></td>
                                <td>{{ $lista === 'cnpq' ? $item->caminho() : $item->nome }}</td>
                                <td>{{ $lista === 'cnpq' ? \App\Models\AreaCnpq::NIVEIS[$item->nivel] : ($item->secao ?? '—') }}</td>
                                <td><span class="badge text-bg-{{ $item->ativo ? 'success' : 'secondary' }}">{{ $item->ativo ? 'Ativo' : 'Inativo' }}</span></td>
                                <td class="text-end pe-3 text-nowrap">
                                    <a href="{{ route('tabelas-lattes.edit', ['lista' => $lista, 'id' => $item->id]) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                    <form method="POST" action="{{ route('tabelas-lattes.alternar', ['lista' => $lista, 'id' => $item->id]) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-sm btn-outline-secondary">{{ $item->ativo ? 'Desativar' : 'Ativar' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Nenhum item encontrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $itens->links() }}</div>
    </div>
</x-app-layout>
