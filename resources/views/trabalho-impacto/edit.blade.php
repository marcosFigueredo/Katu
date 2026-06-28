<x-app-layout>
    <div class="container-fluid" style="max-width: 800px;">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('meu-trabalho.index') }}">Meu Trabalho</a></li>
                <li class="breadcrumb-item active">Impacto da Dissertação</li>
            </ol>
        </nav>

        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Impacto e Aplicação da Dissertação</h1>
            <p class="text-muted mb-0 small">
                Descreva as dimensões de impacto do seu trabalho. Marque as que se aplicam e descreva brevemente.
            </p>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('trabalho-impacto.update') }}">
            @csrf @method('PUT')

            @php
            $dimensoes = [
                'cientifico'    => ['titulo' => 'Impacto Científico',      'icone' => 'bi-journal-richtext',
                                    'help'   => 'Contribuição para o avanço do conhecimento científico na área.'],
                'metodologico'  => ['titulo' => 'Impacto Metodológico',    'icone' => 'bi-diagram-3',
                                    'help'   => 'Proposta de novos métodos, técnicas ou abordagens de pesquisa.'],
                'tecnologico'   => ['titulo' => 'Impacto Tecnológico',     'icone' => 'bi-cpu',
                                    'help'   => 'Desenvolvimento de tecnologias, sistemas ou ferramentas aplicáveis.'],
                'social'        => ['titulo' => 'Impacto Social',          'icone' => 'bi-people-fill',
                                    'help'   => 'Benefícios diretos ou indiretos para a sociedade ou comunidades.'],
                'ambiental'     => ['titulo' => 'Impacto Ambiental',       'icone' => 'bi-tree-fill',
                                    'help'   => 'Contribuições para a sustentabilidade ou preservação ambiental.'],
                'formativo'     => ['titulo' => 'Impacto Formativo',       'icone' => 'bi-mortarboard-fill',
                                    'help'   => 'Contribuições para a formação e capacitação de profissionais.'],
                'institucional' => ['titulo' => 'Impacto Institucional',   'icone' => 'bi-bank2',
                                    'help'   => 'Benefícios para instituições, políticas públicas ou organizações.'],
            ];
            @endphp

            @foreach ($dimensoes as $chave => $dim)
                @php
                    $aplicavelField  = "impacto_{$chave}_aplicavel";
                    $descricaoField  = "impacto_{$chave}_descricao";
                    $aplicavelValor  = old($aplicavelField, $impacto->$aplicavelField ?? false);
                    $descricaoValor  = old($descricaoField, $impacto->$descricaoField ?? '');
                @endphp
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox"
                                   id="{{ $aplicavelField }}" name="{{ $aplicavelField }}"
                                   value="1" {{ $aplicavelValor ? 'checked' : '' }}
                                   onchange="toggleDesc('{{ $chave }}', this.checked)">
                            <label class="form-check-label fw-semibold" for="{{ $aplicavelField }}">
                                <i class="bi {{ $dim['icone'] }} me-2" style="color:#00303A;"></i>
                                {{ $dim['titulo'] }}
                            </label>
                        </div>
                        <p class="text-muted small mb-3">{{ $dim['help'] }}</p>
                        <div id="desc_{{ $chave }}" style="{{ $aplicavelValor ? '' : 'display:none;' }}">
                            <textarea name="{{ $descricaoField }}"
                                      class="form-control @error($descricaoField) is-invalid @enderror"
                                      rows="3"
                                      placeholder="Descreva brevemente como este trabalho gera impacto {{ strtolower($dim['titulo']) }}…"
                                      maxlength="2000">{{ $descricaoValor }}</textarea>
                            @error($descricaoField)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="d-flex justify-content-end gap-2 mt-3 mb-5">
                <a href="{{ route('meu-trabalho.index') }}" class="btn btn-outline-secondary">Voltar</a>
                <button type="submit" class="btn btn-primary fw-semibold">
                    <i class="bi bi-floppy-fill me-2"></i>Salvar dimensões de impacto
                </button>
            </div>
        </form>
    </div>

    <script>
    function toggleDesc(chave, visible) {
        const el = document.getElementById('desc_' + chave);
        if (el) el.style.display = visible ? '' : 'none';
    }
    </script>
</x-app-layout>
