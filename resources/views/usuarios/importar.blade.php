<x-app-layout>
    <div class="container-fluid">
        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Importar discentes</h1>
            <p class="text-muted mb-0">{{ $programa->nome }} — cadastro em lote a partir de um arquivo CSV.</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $erro)
                        <li>{{ $erro }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold">Enviar arquivo</div>
                    <div class="card-body">
                        @if ($cursos->isEmpty())
                            <div class="alert alert-warning mb-0">
                                Cadastre os <a href="{{ route('programa-cursos.index') }}">cursos do programa</a> antes de importar discentes.
                            </div>
                        @else
                            <form method="POST" action="{{ route('usuarios.importar.analisar') }}" enctype="multipart/form-data">
                                @csrf
                                <label for="arquivo" class="form-label">Arquivo CSV (até 2 MB, até 1.000 discentes)</label>
                                <input id="arquivo" name="arquivo" type="file" accept=".csv,text/csv" class="form-control mb-3" required>
                                <button class="btn btn-primary">Analisar arquivo</button>
                            </form>
                            <p class="small text-muted mt-3 mb-0">
                                Nada é gravado nesta etapa: você verá uma prévia linha a linha e confirmará a importação.
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold">Formato do arquivo</div>
                    <div class="card-body small">
                        <p>A primeira linha é o cabeçalho. O separador pode ser <code>;</code> (padrão do Excel em português) ou <code>,</code>.</p>
                        <table class="table table-sm">
                            <thead class="table-light"><tr><th>Coluna</th><th>Obrigatória</th><th>Regra</th></tr></thead>
                            <tbody>
                                <tr><td><code>nome</code></td><td>sim</td><td>nome completo</td></tr>
                                <tr><td><code>email</code></td><td>sim</td><td>e-mail válido; e-mails já cadastrados são ignorados</td></tr>
                                <tr><td><code>matricula</code></td><td>sim</td><td>única no programa</td></tr>
                                <tr><td><code>curso</code></td><td>sim</td><td>
                                    @foreach ($cursos as $curso)
                                        <code>{{ $curso->identificador }}</code> ({{ $curso->nome }})@if (! $loop->last), @endif
                                    @endforeach
                                </td></tr>
                                <tr><td><code>data_ingresso</code></td><td>sim</td><td>dd/mm/aaaa, não futura</td></tr>
                                <tr><td><code>lattes</code></td><td>sim</td><td>http://lattes.cnpq.br/…</td></tr>
                                <tr><td><code>linha_pesquisa</code></td><td>não</td><td>nome de uma linha de pesquisa do programa</td></tr>
                                <tr><td><code>telefone</code></td><td>não</td><td>até 30 caracteres</td></tr>
                            </tbody>
                        </table>
                        <p class="mb-1">Exemplo:</p>
<pre class="bg-light p-2 rounded mb-2">nome;email;matricula;curso;data_ingresso;lattes;linha_pesquisa;telefone
Ana Souza;ana@exemplo.com;2026001;{{ $cursos->first()?->identificador ?? 'mestrado_academico' }};01/03/2026;http://lattes.cnpq.br/1234567890123456;;(71) 99999-0000</pre>
                        <p class="mb-0 text-muted">O ORCID não é importado: o discente informa no próprio cadastro. Ele é obrigatório para qualificação e defesa.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
