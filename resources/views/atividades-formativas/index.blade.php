@use('App\Models\AtividadeFormativa')
<x-app-layout>
    <div class="container-fluid" style="max-width: 1000px;">
        <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Atividades formativas</h1>
        <p class="text-muted mb-3">
            Atividades que os estudantes de {{ $programa->sigla }} precisam cumprir, em qualquer curso.
            Sem as exigidas, o estudante não consegue enviar o pedido à homologação.
            As informadas pelo estudante ele mesmo lança; as informadas pela Secretaria você registra na homologação.
        </p>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Atividade</th>
                            <th class="text-center">Quantidade</th>
                            <th>Pedido</th>
                            <th>Informada por</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($atividades as $atividade)
                            <tr @class(['text-muted' => ! $atividade->ativo])>
                                <td>
                                    <div class="fw-semibold">{{ $atividade->nome }}
                                        @unless ($atividade->ativo)
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">Inativa</span>
                                        @endunless
                                    </div>
                                    @if ($atividade->detalhes)
                                        <div class="small text-muted">{{ $atividade->detalhes }}</div>
                                    @endif
                                </td>
                                <td class="text-center">{{ $atividade->quantidade_exigida }}</td>
                                <td>{{ AtividadeFormativa::ETAPAS[$atividade->etapa] }}</td>
                                <td>{{ AtividadeFormativa::INFORMADA_POR[$atividade->informada_por] }}</td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse"
                                            data-bs-target="#editar-{{ $atividade->id }}">Editar</button>
                                    <form method="POST" action="{{ route('atividades-formativas.inativar', $atividade) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">{{ $atividade->ativo ? 'Inativar' : 'Reativar' }}</button>
                                    </form>
                                </td>
                            </tr>
                            <tr class="collapse" id="editar-{{ $atividade->id }}">
                                <td colspan="5" class="bg-light">
                                    <form method="POST" action="{{ route('atividades-formativas.update', $atividade) }}">
                                        @csrf
                                        @method('PUT')
                                        @include('atividades-formativas._campos', ['atividade' => $atividade])
                                        <button class="btn btn-sm btn-primary mt-2">Salvar</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm card-body">
            <h2 class="h6 fw-bold mb-3" style="color:#00303A;">Nova atividade</h2>
            <form method="POST" action="{{ route('atividades-formativas.store') }}">
                @csrf
                @include('atividades-formativas._campos', ['atividade' => new AtividadeFormativa(['quantidade_exigida' => 1, 'etapa' => 'defesa', 'informada_por' => 'estudante'])])
                <button class="btn btn-primary mt-2">Cadastrar</button>
            </form>
        </div>
    </div>
</x-app-layout>
