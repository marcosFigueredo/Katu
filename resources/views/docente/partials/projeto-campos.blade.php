{{-- Campos do projeto de pesquisa; com $projeto preenche para correção. --}}
@php
    $valor = fn ($campo) => $projeto ? old($campo, $campo === 'inicio_em' || $campo === 'fim_em' ? $projeto->{$campo}?->format('Y-m-d') : $projeto->{$campo}) : old($campo);
@endphp
<div class="mb-2">
    <label for="{{ $prefixoId }}_titulo" class="form-label small fw-semibold">Título</label>
    <input id="{{ $prefixoId }}_titulo" name="titulo" type="text" maxlength="500" required class="form-control" value="{{ $valor('titulo') }}">
</div>
<div class="mb-2">
    <label for="{{ $prefixoId }}_descricao" class="form-label small fw-semibold">Descrição (objetivos do projeto)</label>
    <textarea id="{{ $prefixoId }}_descricao" name="descricao" rows="5" maxlength="5000" required class="form-control">{{ $valor('descricao') }}</textarea>
</div>
<div class="row g-2 mb-3">
    <div class="col-sm-6">
        <label for="{{ $prefixoId }}_inicio" class="form-label small fw-semibold">Início</label>
        <input id="{{ $prefixoId }}_inicio" name="inicio_em" type="date" required class="form-control" value="{{ $valor('inicio_em') }}">
    </div>
    <div class="col-sm-6">
        <label for="{{ $prefixoId }}_fim" class="form-label small fw-semibold">Fim previsto <span class="text-muted fw-normal">(vazio = em andamento)</span></label>
        <input id="{{ $prefixoId }}_fim" name="fim_em" type="date" class="form-control" value="{{ $valor('fim_em') }}">
    </div>
</div>
