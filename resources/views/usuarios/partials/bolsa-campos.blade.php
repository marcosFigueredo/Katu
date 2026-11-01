{{-- Campos da bolsa. $nome(campo) dá o name do input (com ou sem prefixo); $bolsa preenche para edição. --}}
@use('App\Models\Bolsa')
@php
    $nome ??= fn ($campo) => $campo;
    $v = fn ($campo) => old($nome($campo), $bolsa ? ($bolsa->{$campo} instanceof \Carbon\CarbonInterface ? $bolsa->{$campo}->format('Y-m-d') : $bolsa->{$campo}) : null);
    $id = fn ($campo) => $idBase.'_'.$campo;
@endphp
<div class="row g-2">
    <div class="col-md-4">
        <label for="{{ $id('agencia') }}" class="form-label small fw-semibold">Agência</label>
        <select id="{{ $id('agencia') }}" name="{{ $nome('agencia') }}" class="form-select form-select-sm">
            <option value="">Selecione…</option>
            @foreach (Bolsa::AGENCIAS as $valor => $rotulo)
                <option value="{{ $valor }}" @selected($v('agencia') === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label for="{{ $id('agencia_outra') }}" class="form-label small fw-semibold">Nome da agência <span class="text-muted fw-normal">(se "Outra")</span></label>
        <input id="{{ $id('agencia_outra') }}" name="{{ $nome('agencia_outra') }}" type="text" maxlength="150" class="form-control form-control-sm" value="{{ $v('agencia_outra') }}">
    </div>
    <div class="col-md-4">
        <label for="{{ $id('modalidade') }}" class="form-label small fw-semibold">Modalidade</label>
        <select id="{{ $id('modalidade') }}" name="{{ $nome('modalidade') }}" class="form-select form-select-sm">
            <option value="">Selecione…</option>
            @foreach (Bolsa::MODALIDADES as $valor => $rotulo)
                <option value="{{ $valor }}" @selected($v('modalidade') === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label for="{{ $id('numero_processo') }}" class="form-label small fw-semibold">Nº do processo/termo <span class="text-muted fw-normal">(opcional)</span></label>
        <input id="{{ $id('numero_processo') }}" name="{{ $nome('numero_processo') }}" type="text" maxlength="100" class="form-control form-control-sm" value="{{ $v('numero_processo') }}">
    </div>
    <div class="col-md-4">
        <label for="{{ $id('inicio_em') }}" class="form-label small fw-semibold">Início</label>
        <input id="{{ $id('inicio_em') }}" name="{{ $nome('inicio_em') }}" type="date" class="form-control form-control-sm" value="{{ $v('inicio_em') }}">
    </div>
    <div class="col-md-4">
        <label for="{{ $id('fim_em') }}" class="form-label small fw-semibold">Fim <span class="text-muted fw-normal">(opcional)</span></label>
        <input id="{{ $id('fim_em') }}" name="{{ $nome('fim_em') }}" type="date" class="form-control form-control-sm" value="{{ $v('fim_em') }}">
    </div>
</div>
