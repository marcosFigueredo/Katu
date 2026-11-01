@use('App\Models\AvaliacaoOrientando')
<x-app-layout>
    @php $discente = $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user; @endphp

    <div class="container-fluid" style="max-width:820px;">
        <a href="{{ route('avaliacoes.banca', $banca) }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Voltar à banca
        </a>
        <h1 class="h3 fw-bold text-dark mb-0 mt-1">Avaliação do orientando</h1>
        <p class="text-muted small mb-3">Banca de defesa de {{ $discente?->name ?? '—' }}</p>

        <div class="alert alert-info small d-flex gap-2">
            <i class="bi bi-shield-lock-fill mt-1"></i>
            <div>
                Avalie o seu orientando como pesquisador, de 0 a 10 em cada elemento. Responda <strong>todos</strong> e envie de uma vez:
                depois de enviada, a avaliação não pode ser alterada. Ela não entra no resultado da banca e é privada à CPA — nem a banca nem a Secretaria a veem.
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('avaliacao-orientando.store', $banca) }}" id="form-avaliacao-orientando">
            @csrf
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    @foreach (AvaliacaoOrientando::ELEMENTOS as $elemento => $rotulo)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2 gap-3">
                            <label class="fw-semibold mb-0" for="nota-{{ $elemento }}">{{ $rotulo }}</label>
                            <input type="number" name="notas[{{ $elemento }}]" id="nota-{{ $elemento }}" class="form-control form-control-sm"
                                   style="max-width:7rem;" min="0" max="10" step="0.5" required placeholder="0 a 10" value="{{ old("notas.{$elemento}") }}">
                        </div>
                    @endforeach
                    <label class="form-label small mt-3" for="observacoes">Observações (opcional)</label>
                    <textarea name="observacoes" id="observacoes" class="form-control" rows="3" maxlength="5000">{{ old('observacoes') }}</textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end mb-5">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-enviar-orientando">Enviar avaliação</button>
            </div>

            <div class="modal fade" id="modal-enviar-orientando" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Enviar a avaliação do orientando?</h5></div>
                        <div class="modal-body">Depois de enviada, não pode ser alterada.</div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button>
                            <button type="submit" class="btn btn-primary">Enviar</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
