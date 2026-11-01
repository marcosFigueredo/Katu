@use('App\Models\AtividadeFormativa')
@php($sufixo = $atividade->id ?? 'nova')
<div class="row g-2">
    <div class="col-md-6">
        <label class="form-label small fw-semibold" for="nome-{{ $sufixo }}">Nome</label>
        <input type="text" name="nome" id="nome-{{ $sufixo }}" class="form-control form-control-sm" maxlength="150" required value="{{ $atividade->nome }}">
    </div>
    <div class="col-md-2">
        <label class="form-label small fw-semibold" for="quantidade-{{ $sufixo }}">Quantidade</label>
        <input type="number" name="quantidade_exigida" id="quantidade-{{ $sufixo }}" class="form-control form-control-sm" min="1" max="50" required value="{{ $atividade->quantidade_exigida }}">
    </div>
    <div class="col-md-2">
        <label class="form-label small fw-semibold" for="etapa-{{ $sufixo }}">Pedido</label>
        <select name="etapa" id="etapa-{{ $sufixo }}" class="form-select form-select-sm">
            @foreach (AtividadeFormativa::ETAPAS as $valor => $rotulo)
                <option value="{{ $valor }}" @selected($atividade->etapa === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small fw-semibold" for="informada-{{ $sufixo }}">Informada por</label>
        <select name="informada_por" id="informada-{{ $sufixo }}" class="form-select form-select-sm">
            @foreach (AtividadeFormativa::INFORMADA_POR as $valor => $rotulo)
                <option value="{{ $valor }}" @selected($atividade->informada_por === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label small fw-semibold" for="detalhes-{{ $sufixo }}">Detalhes (o que conta)</label>
        <textarea name="detalhes" id="detalhes-{{ $sufixo }}" class="form-control form-control-sm" rows="2" maxlength="2000">{{ $atividade->detalhes }}</textarea>
    </div>
</div>
