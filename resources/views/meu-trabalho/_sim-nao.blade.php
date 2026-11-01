{{-- Par Sim/Não de uma pergunta das Informações adicionais; $campo, $valor (0|1|null), $modelo (variável Alpine). --}}
<div class="d-flex gap-3">
    <div class="form-check">
        <input class="form-check-input" type="radio" name="{{ $campo }}" id="{{ $campo }}-1" value="1" @checked($valor === 1) required x-model="{{ $modelo }}">
        <label class="form-check-label" for="{{ $campo }}-1">Sim</label>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="{{ $campo }}" id="{{ $campo }}-0" value="0" @checked($valor === 0) x-model="{{ $modelo }}">
        <label class="form-check-label" for="{{ $campo }}-0">Não</label>
    </div>
</div>
