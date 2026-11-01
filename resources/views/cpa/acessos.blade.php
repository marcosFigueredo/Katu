<x-app-layout>
    <div class="container-fluid" style="max-width: 1000px;">
        <a href="{{ route('membros-cpa.index') }}" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Membros da CPA</a>
        <h1 class="h3 fw-bold mt-2 mb-1" style="color:#00303A;">Acessos à área da CPA</h1>
        <p class="text-muted mb-3">Registro de auditoria: quem acessou, qual página, com quais filtros e quando (últimos 500).</p>

        <div class="table-responsive">
            <table class="table table-sm align-middle small">
                <thead><tr><th>Data</th><th>Membro</th><th>Página</th><th>Filtros</th></tr></thead>
                <tbody>
                    @forelse ($acessos as $acesso)
                        <tr>
                            <td>{{ $acesso->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $acesso->user?->name }}</td>
                            <td>{{ $acesso->pagina }}</td>
                            <td><code>{{ $acesso->filtros ? json_encode($acesso->filtros, JSON_UNESCAPED_UNICODE) : '—' }}</code></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">Nenhum acesso registrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
