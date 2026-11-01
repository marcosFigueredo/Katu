{{-- Nome e datas do quadriênio. --}}
<div class="col-md-6">
    <label for="{{ $idBase }}_nome" class="form-label small fw-semibold">Nome</label>
    <input id="{{ $idBase }}_nome" name="nome" type="text" maxlength="100" required class="form-control form-control-sm"
           value="{{ old('nome', $quadrienio?->nome) }}" placeholder="Ex.: Quadriênio 2025–2028">
</div>
<div class="col-md-3">
    <label for="{{ $idBase }}_inicio" class="form-label small fw-semibold">Início</label>
    <input id="{{ $idBase }}_inicio" name="inicio_em" type="date" required class="form-control form-control-sm"
           value="{{ old('inicio_em', $quadrienio?->inicio_em?->format('Y-m-d')) }}">
</div>
<div class="col-md-3">
    <label for="{{ $idBase }}_fim" class="form-label small fw-semibold">Fim</label>
    <input id="{{ $idBase }}_fim" name="fim_em" type="date" required class="form-control form-control-sm"
           value="{{ old('fim_em', $quadrienio?->fim_em?->format('Y-m-d')) }}">
</div>
