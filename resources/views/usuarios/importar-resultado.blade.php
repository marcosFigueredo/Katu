<x-app-layout>
    <div class="container-fluid">
        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Importação concluída</h1>
        </div>

        <div class="alert alert-success">
            <strong>{{ $importados }} importado(s).</strong>
            Os e-mails de primeiro acesso foram enfileirados.
        </div>

        @if ($naoImportadas->isNotEmpty())
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">
                    Linhas não importadas ({{ $naoImportadas->count() }}) — corrija o arquivo e envie novamente
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th class="ps-3">Linha</th><th>Nome</th><th>E-mail</th><th>Motivo</th></tr></thead>
                        <tbody>
                            @foreach ($naoImportadas as $linha)
                                <tr>
                                    <td class="ps-3">{{ $linha['numero'] }}</td>
                                    <td>{{ $linha['dados']['nome'] }}</td>
                                    <td>{{ $linha['dados']['email'] }}</td>
                                    <td class="small">{{ implode(' ', $linha['erros']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="d-flex gap-2">
            <a href="{{ route('usuarios.index') }}" class="btn btn-primary">Ver usuários</a>
            <a href="{{ route('usuarios.importar') }}" class="btn btn-outline-secondary">Importar outro arquivo</a>
        </div>
    </div>
</x-app-layout>
