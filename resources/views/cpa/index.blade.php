<x-app-layout>
    <div class="container-fluid" style="max-width: 1200px;">
        <h1 class="h3 fw-bold mb-1" style="color:#00303A;">CPA — Qualidade das dissertações e teses</h1>
        <p class="text-muted mb-3">
            {{ $programa->nome }}. Dados gerais e calculados do quadriênio: nenhum trabalho, estudante ou docente é identificado.
        </p>
        @include('cpa._nav')

        @if (! $periodo)
            <div class="alert alert-info">Nenhum quadriênio cadastrado. A Secretaria cadastra em Configurações → Quadriênios.</div>
        @else
            @php $p = $painel['panorama']; @endphp

            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-md-4">
                    <label for="periodo" class="form-label small fw-semibold">Quadriênio</label>
                    <select id="periodo" name="periodo" class="form-select form-select-sm">
                        @foreach ($periodos as $q)
                            <option value="{{ $q->id }}" @selected($q->id === $periodo->id)>{{ $q->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="linhas" class="form-label small fw-semibold">Linhas de pesquisa</label>
                    <select id="linhas" name="linhas[]" class="form-select form-select-sm" multiple size="3">
                        @foreach ($linhas as $linha)
                            <option value="{{ $linha->id }}" @selected(in_array($linha->id, $filtros['linhas'], true))>{{ $linha->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="nivel" class="form-label small fw-semibold">Nível</label>
                    <select id="nivel" name="nivel" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach (\App\Services\CpaPainelService::NIVEIS as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected($filtros['nivel'] === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filtrar</button></div>
            </form>
            <div class="d-flex gap-2 mb-3">
                <a href="{{ route('cpa.exportar', request()->query()) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Baixar planilha</a>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir / PDF</button>
            </div>

            @if ($painel['conflito'] > 0)
                <div class="alert alert-warning small">
                    <i class="bi bi-person-exclamation me-1"></i>Este recorte inclui {{ $painel['conflito'] }} trabalho(s) em que você participou (orientação ou banca).
                    A análise segue a regra de conflito de interesse e seu acesso fica registrado.
                </div>
            @endif

            <h2 class="h5 fw-bold mt-3">Panorama do quadriênio</h2>
            @if ($p['n'] === 0)
                <div class="alert alert-light">Sem trabalhos neste recorte.</div>
            @else
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="small text-muted">Qualidade do programa — {{ $periodo->nome }}</div>
                                <div class="display-6 fw-bold">{{ $p['indice'] !== null ? number_format($p['indice'], 2, ',', '') : '—' }}</div>
                                @include('cpa._classe', ['classe' => $p['classe']])
                                @include('cpa._intervalo', ['intervalo' => $p['intervalo'], 'fronteira' => $p['fronteira']])
                                <div class="small text-muted mt-2">{{ $p['n'] }} defesa(s) finalizada(s) no recorte.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="small text-muted mb-2">Trabalhos por classe</div>
                                @php $total = max(1, array_sum($p['distribuicao'])); @endphp
                                @foreach ($classes as $n => $nome)
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <div style="width: 150px;" class="small">{{ $n }}. {{ $nome }}</div>
                                        <div class="progress flex-grow-1" style="height: 10px;" role="progressbar" aria-label="{{ $nome }}"
                                             aria-valuenow="{{ $p['distribuicao'][$n] }}" aria-valuemin="0" aria-valuemax="{{ $total }}">
                                            <div class="progress-bar" style="width: {{ $p['distribuicao'][$n] / $total * 100 }}%"></div>
                                        </div>
                                        <div class="small" style="width: 2rem;">{{ $p['distribuicao'][$n] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <h3 class="h6 fw-bold">Perfil por dimensão</h3>
                <p class="small text-muted">"Programa" combina as fontes com os pesos da CPA. Autoavaliações e espelho entram só aqui, agregados.</p>
                <div class="table-responsive mb-4">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Dimensão</th>
                                <th style="min-width: 140px;">Programa</th>
                                @foreach ($fontes as $rotulo)
                                    <th class="small" style="min-width: 130px;">{{ $rotulo }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dimensoes as $codigo => $nome)
                                <tr>
                                    <td>{{ $nome }}</td>
                                    <td>@include('cpa._barra', ['nota' => $p['dimensoes'][$codigo]])</td>
                                    @foreach (array_keys($fontes) as $fonte)
                                        <td>@include('cpa._barra', ['nota' => $p['fontes'][$fonte][$codigo]])</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h3 class="h6 fw-bold">Distribuição das notas dos trabalhos por dimensão</h3>
                <div class="table-responsive mb-4">
                    <table class="table table-sm align-middle small">
                        <thead><tr><th>Dimensão</th><th>n</th><th>Mínimo</th><th>Q1</th><th>Mediana</th><th>Q3</th><th>Máximo</th></tr></thead>
                        <tbody>
                            @foreach ($dimensoes as $codigo => $nome)
                                @php $r = $p['resumo'][$codigo]; @endphp
                                <tr>
                                    <td>{{ $nome }}</td>
                                    @if ($r)
                                        <td>{{ $r['n'] }}</td>
                                        @foreach (['min', 'q1', 'mediana', 'q3', 'max'] as $chave)
                                            <td>{{ number_format($r[$chave], 1, ',', '') }}</td>
                                        @endforeach
                                    @else
                                        <td colspan="6" class="text-muted">—</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h2 class="h5 fw-bold">Evolução ano a ano</h2>
                <div class="table-responsive mb-4">
                    <table class="table table-sm align-middle small">
                        <thead>
                            <tr>
                                <th>Ano</th><th>n</th><th>Índice (mediana)</th>
                                @foreach ($dimensoes as $nome)
                                    <th title="{{ $nome }}">{{ \Illuminate\Support\Str::limit($nome, 12) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($painel['evolucao'] as $ano => $linhaAno)
                                <tr>
                                    <td>{{ $ano }}</td>
                                    <td>{{ $linhaAno['n'] }}</td>
                                    <td>{{ $linhaAno['indice'] !== null ? number_format($linhaAno['indice'], 2, ',', '') : '—' }}</td>
                                    @foreach (array_keys($dimensoes) as $codigo)
                                        <td>{{ $linhaAno['dimensoes'][$codigo] !== null ? number_format($linhaAno['dimensoes'][$codigo], 1, ',', '') : '—' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h2 class="h5 fw-bold">Recortes</h2>
                <div class="row g-3 mb-4">
                    @foreach (['linha' => 'Linha de pesquisa', 'area' => 'Área de concentração', 'nivel' => 'Nível', 'bolsa' => 'Bolsa'] as $tipo => $titulo)
                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h3 class="h6 fw-bold">{{ $titulo }}</h3>
                                    <table class="table table-sm small mb-0">
                                        <thead><tr><th></th><th>n</th><th>Índice: mediana (Q1–Q3)</th><th>Alta/Boa</th></tr></thead>
                                        <tbody>
                                            @foreach ($painel['recortes'][$tipo] ?? [] as $rotulo => $g)
                                                <tr>
                                                    <td>{{ $rotulo }}</td>
                                                    <td>{{ $g['n'] }}</td>
                                                    <td>
                                                        {{ $g['mediana'] !== null ? number_format($g['mediana'], 2, ',', '') : '—' }}
                                                        @if ($g['q1'] !== null)
                                                            <span class="text-muted">({{ number_format($g['q1'], 2, ',', '') }}–{{ number_format($g['q3'], 2, ',', '') }})</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $g['distribuicao'][1] + $g['distribuicao'][2] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

            @endif

            @include('cpa._analises')
        @endif
    </div>
</x-app-layout>
