<x-app-layout>
    <div class="container-fluid">
        <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Convidar membro externo</h1>
        <p class="text-muted mb-4">{{ $programa?->nome ?? 'Nenhum programa ativo' }}</p>
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <div class="card border-0 shadow-sm" style="max-width:720px;">
            <div class="card-body">
                <form method="POST" action="{{ route('membros-externos.convites.store') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">Nome completo</label><input class="form-control" name="nome" value="{{ old('nome') }}" required></div>
                    <div class="mb-3"><label class="form-label">E-mail</label><input type="email" class="form-control" name="email" value="{{ old('email') }}" required></div>
                    <div class="mb-4"><label class="form-label">Validade do convite</label><input type="date" class="form-control" name="expira_em" value="{{ old('expira_em', now()->addDays(7)->toDateString()) }}" required></div>
                    <a href="{{ route('membros-externos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    <button class="btn btn-primary">Enviar convite</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
