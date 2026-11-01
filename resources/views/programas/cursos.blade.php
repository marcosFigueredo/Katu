@php
    $siglaInstituicao = $programa->instituicao?->sigla ?? 'instituição';
    $etapas = ['qualificacao' => 'Qualificação', 'defesa' => 'Defesa'];
    $campos = [
        'min_avaliadores' => 'Mín. avaliadores',
        'max_avaliadores' => 'Máx. avaliadores',
        'min_internos' => 'Mín. internos ao programa',
        'min_externos_instituicao' => 'Mín. externos à ' . $siglaInstituicao,
    ];
@endphp
<x-app-layout>
    <div class="container-fluid">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $erro)
                        <li>{{ $erro }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Cursos do programa</h1>
            <p class="text-muted mb-0">
                {{ $programa->nome }} — cada curso (nível × modalidade) tem prazos e regras de composição de banca próprios.
            </p>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Novo curso</div>
            <div class="card-body">
                <form method="POST" action="{{ route('programa-cursos.store') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-3">
                        <label for="nivel" class="form-label">Nível</label>
                        <select id="nivel" name="nivel" class="form-select" required>
                            <option value="mestrado" @selected(old('nivel') === 'mestrado')>Mestrado</option>
                            <option value="doutorado" @selected(old('nivel') === 'doutorado')>Doutorado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="modalidade" class="form-label">Modalidade</label>
                        <select id="modalidade" name="modalidade" class="form-select" required>
                            <option value="academico" @selected(old('modalidade') === 'academico')>Acadêmico</option>
                            <option value="profissional" @selected(old('modalidade') === 'profissional')>Profissional</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="prazo_qualificacao_meses" class="form-label">Prazo qualificação (meses)</label>
                        <input id="prazo_qualificacao_meses" name="prazo_qualificacao_meses" type="number" min="1" max="120"
                               class="form-control" value="{{ old('prazo_qualificacao_meses') }}" placeholder="padrão">
                    </div>
                    <div class="col-md-2">
                        <label for="prazo_defesa_meses" class="form-label">Prazo defesa (meses)</label>
                        <input id="prazo_defesa_meses" name="prazo_defesa_meses" type="number" min="1" max="120"
                               class="form-control" value="{{ old('prazo_defesa_meses') }}" placeholder="padrão">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100">Adicionar curso</button>
                    </div>
                    <div class="col-12 form-text">
                        Padrões: mestrado 18/24 meses e banca de 2 a 5 avaliadores (≥ 1 interno, ≥ 1 externo);
                        doutorado 30/48 meses e banca de 5 avaliadores (≥ 2 internos, ≥ 2 externos). Ajuste abaixo depois de criar.
                    </div>
                </form>
            </div>
        </div>

        @forelse ($cursos as $curso)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">{{ $curso->nome }} <code class="ms-2 small">{{ $curso->identificador }}</code></span>
                    <span class="badge text-bg-{{ $curso->ativo ? 'success' : 'secondary' }}">{{ $curso->ativo ? 'Ativo' : 'Inativo' }}</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('programa-cursos.update', $curso) }}" class="row g-3 align-items-end mb-4">
                        @csrf
                        @method('PUT')
                        <div class="col-md-3">
                            <label class="form-label">Prazo qualificação (meses)</label>
                            <input name="prazo_qualificacao_meses" type="number" min="1" max="120" class="form-control"
                                   value="{{ $curso->prazo_qualificacao_meses }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Prazo defesa (meses)</label>
                            <input name="prazo_defesa_meses" type="number" min="1" max="120" class="form-control"
                                   value="{{ $curso->prazo_defesa_meses }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Publicações mínimas para a defesa</label>
                            <input name="min_publicacoes_defesa" type="number" min="0" max="20" class="form-control"
                                   value="{{ $curso->min_publicacoes_defesa }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Situação</label>
                            <select name="ativo" class="form-select">
                                <option value="1" @selected($curso->ativo)>Ativo</option>
                                <option value="0" @selected(! $curso->ativo)>Inativo</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <button class="btn btn-outline-primary">Salvar curso</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('programa-cursos.regras', $curso) }}">
                        @csrf
                        @method('PUT')
                        <h2 class="h6 fw-bold">Composição da banca</h2>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Etapa</th>
                                        @foreach ($campos as $rotulo)
                                            <th>{{ $rotulo }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($etapas as $etapa => $rotuloEtapa)
                                        <tr>
                                            <td class="fw-semibold">{{ $rotuloEtapa }}</td>
                                            @foreach ($campos as $campo => $rotulo)
                                                <td>
                                                    <input type="number" min="0" max="5" class="form-control form-control-sm"
                                                           name="regras[{{ $etapa }}][{{ $campo }}]"
                                                           value="{{ $curso->regraPara($etapa)?->{$campo} }}" required>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button class="btn btn-sm btn-primary">Salvar regras de banca</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="alert alert-info">Nenhum curso cadastrado. Adicione ao menos um curso para cadastrar discentes.</div>
        @endforelse
    </div>
</x-app-layout>
