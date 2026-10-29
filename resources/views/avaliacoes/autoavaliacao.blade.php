<x-app-layout>
    @php
        $discente = $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user;
    @endphp

    <div class="container-fluid" style="max-width:820px;">

        <div class="mb-1">
            <a href="{{ route('avaliacoes.banca', $banca) }}" class="text-muted text-decoration-none small">
                <i class="bi bi-arrow-left me-1"></i>Voltar à banca
            </a>
        </div>

        <h1 class="h3 fw-bold text-dark mb-0">Autoavaliação do orientador</h1>
        <p class="text-muted small mb-3">{{ $barema->nome }} — banca de defesa de {{ $discente?->name ?? '—' }}</p>

        <div class="alert alert-info small d-flex gap-2">
            <i class="bi bi-shield-lock-fill mt-1"></i>
            <div>
                Avalie a sua atuação na orientação, de 0 a 10 em cada afirmação. Responda <strong>todas</strong> e envie de uma vez:
                depois de enviada, a autoavaliação não pode ser alterada. As respostas são privadas à CPA — nem a banca nem a Secretaria as veem.
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('avaliacoes.autoavaliacao.store', $banca) }}"
              onsubmit="return confirm('Enviar a autoavaliação? Depois de enviada, ela não pode ser alterada.')">
            @csrf

            @foreach ($barema->secoes as $secao)
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header py-2 px-3" style="background:#00303A;">
                        <span class="fw-semibold text-white">{{ $secao->nome }}</span>
                    </div>
                    <div class="card-body p-0">
                        @foreach ($secao->itens as $item)
                            <div class="border-bottom px-3 py-3">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-9">
                                        <label for="nota-{{ $item->id }}" class="mb-0 text-dark">{{ $item->nome }}</label>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" id="nota-{{ $item->id }}" name="notas[{{ $item->id }}]"
                                               value="{{ old("notas.{$item->id}") }}"
                                               min="0" max="{{ (float) $item->nota_maxima }}" step="0.5" required
                                               class="form-control @error("notas.{$item->id}") is-invalid @enderror"
                                               placeholder="0 a {{ rtrim(rtrim(number_format((float) $item->nota_maxima, 2, ',', ''), '0'), ',') }}">
                                        @error("notas.{$item->id}")
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="mb-3">
                <label for="observacoes" class="form-label fw-semibold">Observações (opcional)</label>
                <textarea id="observacoes" name="observacoes" rows="3" maxlength="5000" class="form-control">{{ old('observacoes') }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary fw-semibold mb-5">
                <i class="bi bi-send-check me-1"></i>Concluir e enviar autoavaliação
            </button>
        </form>
    </div>
</x-app-layout>
