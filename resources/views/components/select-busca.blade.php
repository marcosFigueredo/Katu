@props([
    'name',
    'opcoes' => [],          // lista de ['valor' => int|string, 'rotulo' => string, 'detalhe' => ?string]
    'selecionado' => null,
    'placeholder' => 'Digite para buscar…',
    'vazio' => '— Não atribuído —',
    'invalido' => false,
])

@php
    $id = $attributes->get('id', 'sb_' . preg_replace('/\W+/', '_', $name));
    $opcoesJson = collect($opcoes)->map(fn ($o) => [
        'valor' => (string) $o['valor'],
        'rotulo' => $o['rotulo'],
        'detalhe' => $o['detalhe'] ?? null,
    ])->values();
@endphp

<div x-data="{
        aberto: false,
        busca: '',
        valor: @js((string) ($selecionado ?? '')),
        opcoes: @js($opcoesJson),
        ativo: 0,
        normalizar(t) { return (t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); },
        get filtradas() {
            const b = this.normalizar(this.busca);
            return b ? this.opcoes.filter(o => this.normalizar(o.rotulo + ' ' + (o.detalhe || '')).includes(b)) : this.opcoes;
        },
        get rotuloSelecionado() {
            const o = this.opcoes.find(o => o.valor === this.valor);
            return o ? o.rotulo + (o.detalhe ? ' — ' + o.detalhe : '') : '';
        },
        abrir() { this.aberto = true; this.busca = ''; this.ativo = 0; this.$nextTick(() => this.$refs.busca.focus()); },
        escolher(o) { this.valor = o ? o.valor : ''; this.aberto = false; },
        mover(passo) { const n = this.filtradas.length; if (n) this.ativo = (this.ativo + passo + n) % n; },
     }"
     @click.outside="aberto = false"
     @keydown.escape.prevent="aberto = false"
     class="position-relative">

    <input type="hidden" name="{{ $name }}" :value="valor" value="{{ $selecionado }}">

    <button type="button" id="{{ $id }}"
            class="form-select text-start {{ $invalido ? 'is-invalid' : '' }}"
            @click="aberto ? aberto = false : abrir()"
            :aria-expanded="aberto">
        <span x-text="rotuloSelecionado || @js($vazio)" :class="valor ? '' : 'text-muted'">{{ $vazio }}</span>
    </button>

    <div x-show="aberto" x-cloak
         class="position-absolute start-0 end-0 mt-1 bg-white border rounded shadow-sm"
         style="z-index: 1050;">
        <div class="p-2 border-bottom">
            <input type="text" x-ref="busca" x-model="busca" class="form-control form-control-sm"
                   placeholder="{{ $placeholder }}" autocomplete="off"
                   @keydown.arrow-down.prevent="mover(1)"
                   @keydown.arrow-up.prevent="mover(-1)"
                   @keydown.enter.prevent="filtradas[ativo] && escolher(filtradas[ativo])"
                   @input="ativo = 0">
        </div>
        <ul class="list-unstyled mb-0" style="max-height: 240px; overflow-y: auto;">
            <li>
                <button type="button" class="dropdown-item small text-muted" @click="escolher(null)">{{ $vazio }}</button>
            </li>
            <template x-for="(o, i) in filtradas" :key="o.valor">
                <li>
                    <button type="button" class="dropdown-item small"
                            :class="{ 'active': i === ativo, 'fw-semibold': o.valor === valor }"
                            @mouseenter="ativo = i"
                            @click="escolher(o)">
                        <span x-text="o.rotulo"></span>
                        <span class="text-muted" x-show="o.detalhe" x-text="' — ' + o.detalhe"></span>
                    </button>
                </li>
            </template>
            <li x-show="filtradas.length === 0" class="px-3 py-2 small text-muted">Nenhum docente encontrado.</li>
        </ul>
    </div>
</div>
