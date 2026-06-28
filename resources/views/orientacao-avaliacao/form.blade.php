<x-app-layout>
    <div class="container-fluid" style="max-width: 800px;">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('meu-trabalho.index') }}">Meu Trabalho</a></li>
                <li class="breadcrumb-item active">Avaliar Orientação</li>
            </ol>
        </nav>

        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Avaliação da Orientação</h1>
            <p class="text-muted mb-0 small">
                Banca de {{ $banca->tipo === 'qualificacao' ? 'qualificação' : 'defesa' }}
                @if($banca->data_hora) — {{ $banca->data_hora->format('d/m/Y') }} @endif
            </p>
        </div>

        <div class="alert alert-info small mb-4">
            <i class="bi bi-shield-lock-fill me-2"></i>
            <strong>Confidencialidade garantida:</strong> as respostas são agrupadas e nunca divulgadas individualmente.
            O IEO só é exibido após a coleta de no mínimo 3 avaliações.
        </div>

        @if ($orientacao)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body py-3">
                    <span class="text-muted small">Orientador avaliado</span>
                    <p class="fw-semibold mb-0" style="color:#00303A;">
                        {{ $orientacao->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '—' }}
                    </p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('orientacao-avaliacao.store', $banca) }}">
            @csrf

            @php
            $escala = [
                1 => 'Nunca / Muito insatisfatório',
                2 => 'Raramente / Insatisfatório',
                3 => 'Às vezes / Regular',
                4 => 'Frequentemente / Satisfatório',
                5 => 'Sempre / Muito satisfatório',
            ];

            $categorias = [
                1 => [
                    'titulo' => '1. Competência Científica',
                    'itens'  => [
                        '12' => 'O orientador demonstra domínio científico na área do meu trabalho.',
                    ],
                ],
                2 => [
                    'titulo' => '2. Disponibilidade e Comprometimento',
                    'itens'  => [
                        '21' => 'O orientador está disponível para reuniões de orientação.',
                        '22' => 'O orientador cumpre os prazos acordados.',
                    ],
                ],
                3 => [
                    'titulo' => '3. Contribuição à Qualidade do Trabalho',
                    'itens'  => [
                        '31' => 'O orientador contribui efetivamente para a qualidade do trabalho.',
                        '32' => 'Os feedbacks do orientador são claros e construtivos.',
                    ],
                ],
                4 => [
                    'titulo' => '4. Comunicação e Feedback',
                    'itens'  => [
                        '41' => 'A comunicação com o orientador é fácil e produtiva.',
                        '42' => 'O orientador responde às dúvidas de forma satisfatória.',
                    ],
                ],
                5 => [
                    'titulo' => '5. Suporte ao Desenvolvimento Profissional',
                    'itens'  => [
                        '51' => 'O orientador incentiva minha participação em eventos e publicações.',
                        '52' => 'O orientador contribui para meu desenvolvimento como pesquisador(a).',
                    ],
                ],
                6 => [
                    'titulo' => '6. Ética e Responsabilidade',
                    'itens'  => [
                        '61' => 'O orientador respeita minha autoria e contribuições.',
                        '62' => 'O orientador age com ética e integridade em todo o processo.',
                    ],
                ],
            ];
            @endphp

            {{-- Nota automática categoria 1 --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0" style="color:#00303A;">1. Competência Científica</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-secondary small mb-3 py-2">
                        <i class="bi bi-cpu-fill me-2"></i>
                        <strong>Item 1.1 — Produção científica do orientador</strong>:
                        avaliado automaticamente pelo sistema com base no índice H, citações e aderência temática (via ORCID / OpenAlex).
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">1.2 — O orientador demonstra domínio científico na área do meu trabalho.</label>
                        <div class="d-flex gap-2 flex-wrap">
                            @foreach ($escala as $valor => $label)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input @error('item_12') is-invalid @enderror"
                                           type="radio" name="item_12" id="item_12_{{ $valor }}"
                                           value="{{ $valor }}" {{ old('item_12') == $valor ? 'checked' : '' }} required>
                                    <label class="form-check-label small" for="item_12_{{ $valor }}">
                                        <span class="fw-bold">{{ $valor }}</span><br>
                                        <span class="text-muted" style="font-size:.75rem;">{{ $label }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('item_12')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            @foreach (array_slice($categorias, 1, null, true) as $catNum => $cat)
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0" style="color:#00303A;">{{ $cat['titulo'] }}</h6>
                    </div>
                    <div class="card-body">
                        @foreach ($cat['itens'] as $itemKey => $pergunta)
                            @php $fieldName = 'item_' . $itemKey; @endphp
                            <div class="{{ ! $loop->last ? 'mb-4' : '' }}">
                                <label class="form-label fw-semibold">{{ $catNum }}.{{ $loop->index + 1 }} — {{ $pergunta }}</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    @foreach ($escala as $valor => $label)
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input @error($fieldName) is-invalid @enderror"
                                                   type="radio" name="{{ $fieldName }}"
                                                   id="{{ $fieldName }}_{{ $valor }}"
                                                   value="{{ $valor }}"
                                                   {{ old($fieldName) == $valor ? 'checked' : '' }} required>
                                            <label class="form-check-label small" for="{{ $fieldName }}_{{ $valor }}">
                                                <span class="fw-bold">{{ $valor }}</span><br>
                                                <span class="text-muted" style="font-size:.75rem;">{{ $label }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                @error($fieldName)
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="d-flex justify-content-end gap-2 mt-3 mb-5">
                <a href="{{ route('meu-trabalho.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary fw-semibold">
                    <i class="bi bi-check-circle-fill me-2"></i>Enviar avaliação
                </button>
            </div>
        </form>

    </div>
</x-app-layout>
