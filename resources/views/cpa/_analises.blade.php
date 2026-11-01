{{-- Pontos fortes e fracos, confiabilidade e projetos × objetivos (dados gerais, sem identificar trabalho nem docente). --}}
@php
    $fmt = fn ($v, $casas = 1) => $v === null ? '—' : number_format($v, $casas, ',', '');
    $sinal = fn ($v) => $v === null ? '—' : ($v > 0 ? '+' : '').number_format($v, 1, ',', '');
    $conf = $painel['confiabilidade'];
@endphp

<h2 class="h5 fw-bold">Pontos fortes e fracos</h2>
<p class="small text-muted">Perguntas dos baremas com maior e menor mediana no conjunto das avaliações (formulário padrão e sigiloso juntos).</p>
<div class="row g-3 mb-4">
    @foreach (['fortes' => 'Mais bem avaliadas', 'fracos' => 'Menos bem avaliadas'] as $chave => $titulo)
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h3 class="h6 fw-bold">{{ $titulo }}</h3>
                    <table class="table table-sm small mb-0">
                        <thead><tr><th>Pergunta</th><th>Dimensão</th><th>Mediana</th></tr></thead>
                        <tbody>
                            @forelse ($painel['pontos'][$chave] ?? [] as $linha)
                                <tr><td>{{ $linha['pergunta'] }}</td><td>{{ $linha['dimensao'] }}</td><td>{{ $fmt($linha['mediana']) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">—</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach
</div>

<h2 class="h5 fw-bold">Confiabilidade das avaliações</h2>
<p class="small">
    Concordância entre avaliadores: diferença mediana de <strong>{{ $fmt($conf['concordancia']['mediana_amplitude']) }}</strong> ponto(s) entre a maior e a menor nota da mesma pergunta;
    <strong>{{ $fmt($conf['concordancia']['percentual_ate_2']) }}%</strong> das perguntas com diferença de até 2 pontos.
</p>
<div class="table-responsive mb-4">
    <table class="table table-sm align-middle small">
        <thead>
            <tr>
                <th>Dimensão</th>
                <th>Formulário padrão (mediana)</th>
                <th>Formulário sigiloso (mediana)</th>
                <th title="Mediana de (espelho − banca) por defesa">Estudante − banca</th>
                <th title="Mediana de (autoavaliação − banca) por defesa">Orientador − banca</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dimensoes as $codigo => $nome)
                <tr>
                    <td>{{ $nome }}</td>
                    <td>{{ $fmt($conf['aberto_confidencial'][$codigo]['aberto'] ?? null) }}</td>
                    <td>{{ $fmt($conf['aberto_confidencial'][$codigo]['confidencial'] ?? null) }}</td>
                    <td>{{ $sinal($conf['espelho_banca'][$codigo] ?? null) }}</td>
                    <td>{{ $sinal($conf['orientador_banca'][$codigo] ?? null) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<h2 class="h5 fw-bold">Projetos de pesquisa e objetivos indicados pelos estudantes</h2>
<p class="small text-muted">Correlação entre os projetos dos docentes e os objetivos que as pesquisas dos estudantes declaram avançar (sem nomes).</p>
@forelse ($painel['projetos'] as $projeto)
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-body">
            <div class="fw-semibold">{{ $projeto['titulo'] }} <span class="badge text-bg-light">{{ $projeto['situacao'] }}</span></div>
            <p class="small text-muted mb-2" style="white-space: pre-line;">{{ $projeto['descricao'] }}</p>
            @if ($projeto['objetivos'] === [])
                <div class="small text-muted">Nenhum objetivo indicado.</div>
            @else
                <ul class="small mb-0">
                    @foreach ($projeto['objetivos'] as $objetivo)
                        <li>{{ $objetivo['objetivo'] }} @unless ($objetivo['vigente'])<span class="text-muted">(vínculo encerrado)</span>@endunless</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@empty
    <div class="alert alert-light">Nenhum projeto de pesquisa cadastrado.</div>
@endforelse
