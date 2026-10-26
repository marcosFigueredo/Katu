<x-app-layout>
    <div class="container-fluid">
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="mb-4"><h1 class="h3 fw-bold mb-1" style="color:#00303A;">Homologação de bancas</h1><p class="text-muted mb-0">Verifique a composição da banca antes de liberar a avaliação.</p></div>
        <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr><th class="ps-3">Discente</th><th>Etapa</th><th>Status</th><th>Verificações</th><th class="text-end pe-3">Decisão</th></tr></thead><tbody>
            @forelse($bancas as $banca)<tr><td class="ps-3"><strong>{{ $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user?->name ?? '—' }}</strong><br><small class="text-muted">{{ $banca->trabalhoAcademico?->titulo ?? 'Trabalho sem título' }}</small></td><td>{{ ucfirst($banca->tipo) }}<br><small>{{ ucfirst($banca->trabalhoAcademico?->nivel ?? '') }}</small></td><td><span class="badge text-bg-{{ $banca->homologacao_status === 'devolvida' ? 'warning' : 'secondary' }}">{{ ucfirst($banca->homologacao_status) }}</span>@if($banca->homologacao_observacoes)<div class="small text-muted mt-1">{{ $banca->homologacao_observacoes }}</div>@endif</td><td>
                <table class="table table-sm small mt-2 mb-0"><thead><tr><th>Verificação</th><th>Exigido</th><th>Encontrado</th><th></th></tr></thead><tbody>
                    @foreach ($verificacoes[$banca->id] as $item)
                        <tr><td>{{ $item['descricao'] }}</td><td>{{ $item['exigido'] }}</td><td>{{ $item['encontrado'] }}</td><td><i class="bi bi-{{ $item['ok'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' }}"></i></td></tr>
                    @endforeach
                </tbody></table></td><td class="text-end pe-3"><form method="POST" action="{{ route('bancas.homologar', $banca) }}" class="mb-2">@csrf<button class="btn btn-sm btn-success" @disabled($verificacoes[$banca->id]->contains(fn ($i) => ! $i['ok']))>Homologar</button></form><form method="POST" action="{{ route('bancas.devolver', $banca) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input class="form-control form-control-sm mb-1" name="observacoes" placeholder="Motivo da devolução" required><button class="btn btn-sm btn-warning">Devolver</button></form></td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-5">Nenhuma banca aguardando homologação.</td></tr>@endforelse
        </tbody></table></div></div>
    </div>
</x-app-layout>
