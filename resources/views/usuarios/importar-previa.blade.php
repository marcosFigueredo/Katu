@php
    $validas = $linhas->where('status', 'valida')->count();
    $comErro = $linhas->where('status', 'erro')->count();
    $existentes = $linhas->where('status', 'existente')->count();
    $badges = ['valida' => ['success', 'Válida'], 'erro' => ['danger', 'Com erro'], 'existente' => ['secondary', 'Já existente']];
@endphp
<x-app-layout>
    <div class="container-fluid">
        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Prévia da importação</h1>
            <p class="text-muted mb-0">Confira as linhas. Apenas as válidas serão importadas.</p>
        </div>

        <div class="d-flex gap-3 mb-3">
            <span class="badge text-bg-success fs-6">{{ $validas }} válida(s)</span>
            <span class="badge text-bg-danger fs-6">{{ $comErro }} com erro</span>
            <span class="badge text-bg-secondary fs-6">{{ $existentes }} já existente(s)</span>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Linha</th><th>Nome</th><th>E-mail</th><th>Matrícula</th><th>Curso</th><th>Situação</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($linhas as $linha)
                            <tr>
                                <td class="ps-3">{{ $linha['numero'] }}</td>
                                <td>{{ $linha['dados']['nome'] }}</td>
                                <td>{{ $linha['dados']['email'] }}</td>
                                <td>{{ $linha['dados']['matricula'] }}</td>
                                <td>{{ $linha['dados']['curso'] }}</td>
                                <td>
                                    <span class="badge text-bg-{{ $badges[$linha['status']][0] }}">{{ $badges[$linha['status']][1] }}</span>
                                    @foreach ($linha['erros'] as $erro)
                                        <div class="small text-danger">{{ $erro }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('usuarios.importar.confirmar') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <button class="btn btn-primary" @disabled($validas === 0) onclick="this.disabled = true; this.form.submit();">
                    Confirmar importação de {{ $validas }} discente(s)
                </button>
            </form>
            <a href="{{ route('usuarios.importar') }}" class="btn btn-outline-secondary">Enviar outro arquivo</a>
        </div>
    </div>
</x-app-layout>
