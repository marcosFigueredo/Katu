{{-- Campos do período de avaliação e das faixas das classes. --}}
@php
    $v = fn ($campo, $padrao = null) => old($campo, $periodo ? ($periodo->{$campo} instanceof \Carbon\CarbonInterface ? $periodo->{$campo}->format('Y-m-d') : $periodo->{$campo}) : $padrao);
    $faixas = [
        'corte_alta' => ['Alta qualidade a partir de', 8.5],
        'corte_boa' => ['Boa a partir de', 7.0],
        'corte_suficiente' => ['Suficiente a partir de', 5.5],
        'corte_pouca' => ['Pouca qualidade a partir de (abaixo: Baixa)', 4.0],
    ];
@endphp
<div class="row g-2">
    <div class="col-md-6">
        <label for="{{ $idBase }}_nome" class="form-label small fw-semibold">Nome</label>
        <input id="{{ $idBase }}_nome" name="nome" type="text" maxlength="100" required class="form-control form-control-sm" value="{{ $v('nome') }}" placeholder="Ex.: Quadriênio 2025–2028">
    </div>
    <div class="col-md-3">
        <label for="{{ $idBase }}_inicio" class="form-label small fw-semibold">Início</label>
        <input id="{{ $idBase }}_inicio" name="inicio_em" type="date" required class="form-control form-control-sm" value="{{ $v('inicio_em') }}">
    </div>
    <div class="col-md-3">
        <label for="{{ $idBase }}_fim" class="form-label small fw-semibold">Fim</label>
        <input id="{{ $idBase }}_fim" name="fim_em" type="date" required class="form-control form-control-sm" value="{{ $v('fim_em') }}">
    </div>
    @foreach ($faixas as $campo => [$rotulo, $padrao])
        <div class="col-md-3">
            <label for="{{ $idBase }}_{{ $campo }}" class="form-label small fw-semibold">{{ $rotulo }}</label>
            <input id="{{ $idBase }}_{{ $campo }}" name="{{ $campo }}" type="number" step="0.01" min="0" max="10" class="form-control form-control-sm" value="{{ $v($campo, $padrao) }}">
        </div>
    @endforeach
</div>
