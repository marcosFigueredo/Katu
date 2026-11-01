<x-app-layout>
    <div class="container-fluid" style="max-width: 1000px;">
        <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Membros da CPA</h1>
        <p class="text-muted mb-3">
            A CPA não avalia trabalhos: define os pesos dos instrumentos e acompanha a qualidade das dissertações e teses do programa.
            Só membros com mandato vigente acessam a área da CPA.
        </p>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 fw-bold">Designar membro</h2>
                <form method="POST" action="{{ route('membros-cpa.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label for="user_id" class="form-label small fw-semibold">Docente</label>
                        <select id="user_id" name="user_id" class="form-select form-select-sm" required>
                            <option value="">Selecione…</option>
                            @foreach ($docentes as $docente)
                                <option value="{{ $docente->id }}" @selected(old('user_id') == $docente->id)>{{ $docente->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="inicio_em" class="form-label small fw-semibold">Início do mandato</label>
                        <input id="inicio_em" name="inicio_em" type="date" required class="form-control form-control-sm" value="{{ old('inicio_em') }}">
                    </div>
                    <div class="col-md-2">
                        <label for="fim_em" class="form-label small fw-semibold">Fim <span class="text-muted fw-normal">(opcional)</span></label>
                        <input id="fim_em" name="fim_em" type="date" class="form-control form-control-sm" value="{{ old('fim_em') }}">
                    </div>
                    <div class="col-md-3">
                        <label for="numero_ato" class="form-label small fw-semibold">Número do ato</label>
                        <input id="numero_ato" name="numero_ato" type="text" maxlength="150" required class="form-control form-control-sm" value="{{ old('numero_ato') }}" placeholder="Ex.: Portaria nº 10/2026">
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-sm btn-primary w-100">Designar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Membro</th><th>Mandato</th><th>Ato</th><th>Situação</th><th></th></tr></thead>
                <tbody>
                    @forelse ($membros as $membro)
                        <tr>
                            <td>{{ $membro->user?->name }}</td>
                            <td>{{ $membro->inicio_em->format('d/m/Y') }} a {{ $membro->fim_em?->format('d/m/Y') ?? 'em aberto' }}</td>
                            <td>{{ $membro->numero_ato }}</td>
                            <td>
                                @if ($membro->vigente())
                                    <span class="badge bg-success-subtle text-success-emphasis">Vigente</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Fora do mandato</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($membro->vigente())
                                    <form method="POST" action="{{ route('membros-cpa.encerrar', $membro) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">Encerrar mandato</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">Nenhum membro designado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
