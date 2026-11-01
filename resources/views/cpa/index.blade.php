<x-app-layout>
    <div class="container-fluid" style="max-width: 1200px;">
        <h1 class="h3 fw-bold mb-1" style="color:#00303A;">CPA — Qualidade das dissertações e teses</h1>
        <p class="text-muted mb-3">{{ $programa->nome }}</p>
        @include('cpa._nav')

        @if (! $periodo)
            <div class="alert alert-info">Crie um período de avaliação em <a href="{{ route('cpa.periodos.index') }}">Períodos e pesos</a> para ver os resultados.</div>
        @else
            <form method="GET" class="d-flex gap-2 align-items-center mb-3">
                <label for="periodo" class="small fw-semibold">Período</label>
                <select id="periodo" name="periodo" class="form-select form-select-sm" style="max-width: 320px;" onchange="this.form.submit()">
                    @foreach ($periodos as $p)
                        <option value="{{ $p->id }}" @selected($p->id === $periodo->id)>{{ $p->nome }} ({{ $p->inicio_em->format('Y') }}–{{ $p->fim_em->format('Y') }})</option>
                    @endforeach
                </select>
            </form>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="small text-muted">Qualidade do programa — {{ $periodo->nome }}</div>
                            <div class="display-6 fw-bold">{{ $resultado['indice'] !== null ? number_format($resultado['indice'], 2, ',', '') : '—' }}</div>
                            @include('cpa._classe', ['classe' => $resultado['classe']])
                            @include('cpa._intervalo', ['intervalo' => $resultado['intervalo'], 'fronteira' => $resultado['fronteira']])
                            <div class="small text-muted mt-2">{{ count($resultado['trabalhos']) }} defesa(s) finalizada(s) no período.</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="small text-muted mb-2">Trabalhos por classe</div>
                            @php $total = max(1, array_sum($resultado['distribuicao'])); @endphp
                            @foreach ($classes as $n => $nome)
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <div style="width: 150px;" class="small">{{ $n }}. {{ $nome }}</div>
                                    <div class="progress flex-grow-1" style="height: 10px;" role="progressbar" aria-label="{{ $nome }}"
                                         aria-valuenow="{{ $resultado['distribuicao'][$n] }}" aria-valuemin="0" aria-valuemax="{{ $total }}">
                                        <div class="progress-bar" style="width: {{ $resultado['distribuicao'][$n] / $total * 100 }}%"></div>
                                    </div>
                                    <div class="small" style="width: 2rem;">{{ $resultado['distribuicao'][$n] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <h2 class="h5 fw-bold">Perfil do programa por dimensão</h2>
            <p class="small text-muted">A coluna "Programa" combina as fontes com os pesos definidos pela CPA. Autoavaliações e espelho entram aqui, não na nota de cada trabalho.</p>
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
                                <td>@include('cpa._barra', ['nota' => $resultado['dimensoes'][$codigo]])</td>
                                @foreach (array_keys($fontes) as $fonte)
                                    <td>@include('cpa._barra', ['nota' => $resultado['fontes'][$fonte][$codigo]])</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h2 class="h5 fw-bold">Dissertações e teses do período</h2>
            <div class="table-responsive mb-5">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Estudante e título</th>
                            <th>Defesa</th>
                            <th>Índice</th>
                            <th>Classe</th>
                            <th class="small" title="Publicações declaradas e quantas o OpenAlex encontrou (informativo)">Publicações</th>
                            @foreach ($dimensoes as $nome)
                                <th class="small" title="{{ $nome }}">{{ \Illuminate\Support\Str::limit($nome, 12) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($resultado['trabalhos'] as $item)
                            @php $trabalho = $item['banca']->trabalhoAcademico; @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $trabalho?->discenteVinculo?->discenteProfile?->user?->name ?? '—' }}</div>
                                    <div class="small text-muted">{{ \Illuminate\Support\Str::limit($trabalho?->titulo ?? 'Título não informado', 80) }}</div>
                                    @foreach ($item['regras'] as $regra)
                                        <div class="small text-danger">{{ $regra }}</div>
                                    @endforeach
                                </td>
                                <td class="small">{{ $item['banca']->data_hora?->format('d/m/Y') }}</td>
                                <td>{{ $item['indice'] !== null ? number_format($item['indice'], 2, ',', '') : '—' }}</td>
                                <td>
                                    @include('cpa._classe', ['classe' => $item['classe']])
                                    @include('cpa._intervalo', ['intervalo' => $item['intervalo'] ?? null, 'fronteira' => $item['fronteira'] ?? null])
                                </td>
                                @php $pubs = $trabalho?->publicacoes ?? collect(); @endphp
                                <td class="small">{{ $pubs->count() }} <span class="text-muted">({{ $pubs->where('openalex_situacao', 'encontrada')->count() }} no OpenAlex)</span></td>
                                @foreach (array_keys($dimensoes) as $codigo)
                                    <td class="small">{{ $item['dimensoes'][$codigo] !== null ? number_format($item['dimensoes'][$codigo], 1, ',', '') : '—' }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ 5 + count($dimensoes) }}" class="text-muted">Nenhuma defesa finalizada neste período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
