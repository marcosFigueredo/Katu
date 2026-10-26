<x-app-layout>
    <div class="container-fluid">
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Critérios da CPA</h1>
                <p class="text-muted mb-0">Perguntas de sim ou não que a Secretaria responde na homologação da banca. Uma pergunta nunca é apagada: ao ser inativada, guarda a data.</p>
            </div>
            <a class="btn btn-primary" href="{{ route('criterios-cpa.create') }}">Nova pergunta</a>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">{{ $programa?->nome ?? 'Nenhum programa ativo' }}</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Pergunta</th><th>Etapa</th><th>Nível</th><th>Criada em</th><th>Inativada em</th><th>Status</th><th class="text-end pe-3"></th></tr></thead>
                    <tbody>
                    @forelse($criterios as $criterio)
                        <tr>
                            <td class="ps-3">{{ $criterio->pergunta }}</td>
                            <td>{{ match ($criterio->etapa) { 'qualificacao' => 'Qualificação', 'defesa' => 'Defesa', default => 'Qualificação e defesa' } }}</td>
                            <td>{{ match ($criterio->nivel) { 'mestrado' => 'Mestrado', 'doutorado' => 'Doutorado', default => 'Mestrado e doutorado' } }}</td>
                            <td>{{ $criterio->created_at?->format('d/m/Y') }}</td>
                            <td>{{ $criterio->inativado_em?->format('d/m/Y') ?? '—' }}</td>
                            <td><span class="badge text-bg-{{ $criterio->ativo ? 'success' : 'secondary' }}">{{ $criterio->ativo ? 'Ativa' : 'Inativa' }}</span></td>
                            <td class="text-end pe-3">
                                @if($criterio->ativo)
                                    <a href="{{ route('criterios-cpa.edit', $criterio) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                    <form class="d-inline" method="POST" action="{{ route('criterios-cpa.inativar', $criterio) }}" onsubmit="return confirm('Inativar esta pergunta? Ela não poderá ser reativada.')">@csrf<button class="btn btn-sm btn-outline-warning">Inativar</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhuma pergunta cadastrada.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
