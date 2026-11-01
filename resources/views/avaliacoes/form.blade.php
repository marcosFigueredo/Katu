<x-app-layout>
    @php
        $discente      = $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user;
        $trabalho      = $banca->trabalhoAcademico;
        $tipoLabel     = $banca->tipo === 'qualificacao' ? 'Qualificação' : 'Defesa';
        $confidencial  = str_contains($barema->tipo, 'confidencial');
        $titulo        = ($confidencial ? 'Avaliação Confidencial' : 'Avaliação') . ' — ' . $tipoLabel;
    @endphp

    <div class="container-fluid" style="max-width:820px;">

        {{-- Navegação --}}
        <div class="mb-1">
            <a href="{{ route('avaliacoes.banca', $banca) }}" class="text-muted text-decoration-none small">
                <i class="bi bi-arrow-left me-1"></i>Voltar à banca
            </a>
        </div>

        <div class="d-flex align-items-start gap-2 mb-1 flex-wrap">
            <div>
                <h1 class="h3 fw-bold text-dark mb-0">{{ $titulo }}</h1>
                <p class="text-muted small mb-0">{{ $barema->nome }}</p>
            </div>
            @if ($confidencial)
                <span class="badge bg-dark ms-auto align-self-start mt-1">
                    <i class="bi bi-lock-fill me-1"></i>Confidencial
                </span>
            @endif
        </div>

        @if ($somenteLeitura)
            <div class="alert alert-info d-flex align-items-center gap-2 mt-3 mb-4">
                <i class="bi bi-info-circle-fill fs-5"></i>
                <span>
                    @if ($banca->status === 'finalizada')
                        Banca finalizada — avaliação em modo somente leitura.
                    @else
                        Avaliação enviada — somente leitura. Para editar, entre em contato com a secretaria.
                    @endif
                </span>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                {{ $errors->first() }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Info do discente e trabalho --}}
        <div class="card border-0 shadow-sm mt-3 mb-4">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-sm-6">
                        <span class="text-muted small">Discente</span>
                        <p class="fw-semibold mb-0">{{ $discente?->name ?? '—' }}</p>
                    </div>
                    @if ($banca->data_hora)
                        <div class="col-sm-6">
                            <span class="text-muted small">Data da banca</span>
                            <p class="fw-semibold mb-0">{{ $banca->data_hora->format('d/m/Y \à\s H:i') }}</p>
                        </div>
                    @endif
                    @if ($trabalho?->titulo)
                        <div class="col-12">
                            <span class="text-muted small">Título</span>
                            <p class="fw-semibold mb-0">{{ $trabalho->titulo }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @include('avaliacoes.partials.informacoes-estudante')

        {{-- Formulário --}}
        <form method="POST" action="{{ route('avaliacoes.salvar', [$banca, $barema]) }}"
              id="form-avaliacao">
            @csrf

            {{-- Seções e itens do barema --}}
            @foreach ($barema->secoes as $secao)
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header py-2 px-3" style="background:#00303A;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold text-white">{{ $secao->nome }}</span>
                            @if ($secao->peso != 1)
                                <span class="badge bg-white text-dark">
                                    Peso {{ number_format($secao->peso, 1) }}
                                </span>
                            @endif
                        </div>
                        @if ($secao->descricao)
                            <p class="text-white-50 small mb-0 mt-1">{{ $secao->descricao }}</p>
                        @endif
                    </div>

                    <div class="card-body p-0">
                        @foreach ($secao->itens as $item)
                            @php
                                $notaExistente = $notasExistentes[$item->id] ?? null;
                                $notaVal       = old("notas.{$item->id}", $notaExistente?->nota);
                                $obsVal        = old("observacoes.{$item->id}", $notaExistente?->observacao ?? '');
                            @endphp

                            <div class="border-bottom px-3 py-3 item-avaliacao"
                                 data-nota-maxima="{{ $item->nota_maxima }}"
                                 data-peso="{{ $item->peso }}">
                                <div class="row g-2 align-items-start">
                                    <div class="col-md-7">
                                        <p class="fw-semibold mb-0 text-dark">
                                            {{ $item->nome }}
                                            @if ($item->obrigatorio)
                                                <span class="text-danger">*</span>
                                            @endif
                                        </p>
                                        @if ($item->descricao)
                                            <p class="text-muted small mb-0">{{ $item->descricao }}</p>
                                        @endif
                                        @if ($item->indicador_capes)
                                            <span class="badge bg-light text-secondary border small mt-1">
                                                CAPES: {{ $item->indicador_capes }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="col-md-5">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="flex-fill">
                                                <label class="form-label small text-muted mb-1">
                                                    Nota (0 – {{ number_format($item->nota_maxima, 1) }})
                                                </label>
                                                <input type="number"
                                                       name="notas[{{ $item->id }}]"
                                                       class="form-control form-control-sm nota-input"
                                                       min="0"
                                                       max="{{ $item->nota_maxima }}"
                                                       step="0.5"
                                                       value="{{ $notaVal !== null ? number_format($notaVal, 1, '.', '') : '' }}"
                                                       placeholder="—"
                                                       {{ $somenteLeitura ? 'readonly' : '' }}>
                                            </div>
                                            <div class="text-end" style="min-width:60px;">
                                                <span class="text-muted small">de</span><br>
                                                <span class="fw-semibold">{{ number_format($item->nota_maxima, 1) }}</span>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <textarea name="observacoes[{{ $item->id }}]"
                                                      class="form-control form-control-sm"
                                                      rows="2"
                                                      placeholder="Observação (opcional)"
                                                      {{ $somenteLeitura ? 'readonly' : '' }}>{{ $obsVal }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            {{-- Observações gerais --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <label class="form-label fw-semibold">Observações gerais</label>
                    <textarea name="observacoes_gerais"
                              class="form-control"
                              rows="4"
                              placeholder="Comentários finais sobre a avaliação (opcional)"
                              {{ $somenteLeitura ? 'readonly' : '' }}>{{ old('observacoes_gerais', $avaliacao->observacoes_gerais ?? '') }}</textarea>
                </div>
            </div>

            {{-- Resumo da nota --}}
            <div class="card border-0 shadow-sm mb-4" style="border-left:4px solid #00303A !important;">
                <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <p class="text-muted small mb-0">Nota calculada</p>
                        <p class="text-muted small mb-0">
                            (média ponderada dos itens preenchidos)
                        </p>
                    </div>
                    <div class="text-end">
                        <span id="nota-calculada" class="display-6 fw-bold" style="color:#00303A;">—</span>
                        <span class="text-muted fs-5">/ 10,0</span>
                    </div>
                </div>
            </div>

            {{-- Botões --}}
            @if (! $somenteLeitura)
                <div class="d-flex gap-2 flex-wrap mb-5">
                    <button type="submit" name="acao" value="rascunho"
                            class="btn btn-outline-secondary">
                        <i class="bi bi-floppy me-1"></i>Salvar rascunho
                    </button>
                    <button type="submit" name="acao" value="enviar"
                            class="btn"
                            style="background:#00303A; color:#fff;"
                            onclick="return confirm('Confirma o envio da avaliação? Os campos obrigatórios devem estar preenchidos.')">
                        <i class="bi bi-send me-1"></i>Enviar avaliação
                    </button>
                </div>
            @else
                <div class="mb-5">
                    <a href="{{ route('avaliacoes.banca', $banca) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Voltar
                    </a>
                </div>
            @endif

        </form>
    </div>

    <script>
    (function () {
        function calcularNota() {
            let totalObtido   = 0;
            let totalPossivel = 0;
            let algumPreenchido = false;

            document.querySelectorAll('.item-avaliacao').forEach(function (el) {
                const notaMaxima = parseFloat(el.dataset.notaMaxima) || 0;
                const peso       = parseFloat(el.dataset.peso) || 1;
                const input      = el.querySelector('.nota-input');
                const val        = input ? parseFloat(input.value) : NaN;

                totalPossivel += notaMaxima * peso;

                if (! isNaN(val) && val >= 0) {
                    totalObtido += val * peso;
                    algumPreenchido = true;
                }
            });

            const span = document.getElementById('nota-calculada');
            if (! span) return;

            if (! algumPreenchido || totalPossivel <= 0) {
                span.textContent = '—';
                return;
            }

            const nota = (totalObtido / totalPossivel) * 10;
            span.textContent = nota.toFixed(1).replace('.', ',');
        }

        document.querySelectorAll('.nota-input').forEach(function (input) {
            input.addEventListener('input', calcularNota);
        });

        calcularNota();
    })();
    </script>
</x-app-layout>
