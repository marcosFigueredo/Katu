<x-app-layout>
    @php
    $docenteProfile = $usuario->docenteProfile;
    $discenteProfile = $usuario->discenteProfile;

    $docenteVinculo = null;
    if ($docenteProfile && $programaAtual) {
    $docenteVinculo = $docenteProfile->vinculosPrograma
    ->where('programa_id', $programaAtual->id)
    ->first();
    }

    $discenteVinculo = null;
    if ($discenteProfile && $programaAtual) {
    $discenteVinculo = $discenteProfile->vinculosPrograma
    ->where('programa_id', $programaAtual->id)
    ->first();
    }

    $categoriaAtual = old('categoria', $usuario->categoria);
    $tipoVinculoAtual = old('tipo_vinculo', $docenteVinculo->tipo_vinculo ?? 'interno');
    @endphp

    <div class="container-fluid">

        <div class="mb-4">
            <h1 class="h3 mb-1 fw-bold text-dark">
                Editar Usuário
            </h1>

            <p class="text-muted mb-0">
                Atualize os dados cadastrais, acadêmicos e institucionais do usuário.
            </p>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">

                <form method="POST" action="{{ route('usuarios.update', $usuario) }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="name" class="form-label fw-semibold">Nome</label>
                            <input type="text" id="name" name="name" class="form-control"
                                value="{{ old('name', $usuario->name) }}" required>
                            @error('name')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold">E-mail</label>
                            <input type="email" id="email" name="email" class="form-control"
                                value="{{ old('email', $usuario->email) }}" required>
                            @error('email')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="categoria" class="form-label fw-semibold">Tipo de usuário</label>
                            <select id="categoria" name="categoria" class="form-select" required>
                                <option value="sec" {{ $categoriaAtual === 'sec' ? 'selected' : '' }}>
                                    Secretaria
                                </option>
                                <option value="docente" {{ $categoriaAtual === 'docente' ? 'selected' : '' }}>
                                    Docente
                                </option>
                                <option value="discente" {{ $categoriaAtual === 'discente' ? 'selected' : '' }}>
                                    Discente
                                </option>
                            </select>
                            @error('categoria')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div id="campo-tipo-vinculo" class="col-md-4 d-none">
                            <label for="tipo_vinculo" class="form-label fw-semibold">Tipo de vínculo</label>
                            <select id="tipo_vinculo" name="tipo_vinculo" class="form-select">
                                <option value="interno" {{ $tipoVinculoAtual === 'interno' ? 'selected' : '' }}>
                                    Interno
                                </option>
                                <option value="externo" {{ $tipoVinculoAtual === 'externo' ? 'selected' : '' }}>
                                    Externo
                                </option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Programa da sessão</label>
                            <input type="text"
                                class="form-control"
                                value="{{ $programaAtual ? (($programaAtual->sigla ? $programaAtual->sigla . ' - ' : '') . $programaAtual->nome) : 'Nenhum programa vinculado à secretaria' }}"
                                disabled>
                        </div>

                    </div>

                    <hr class="my-4">

                    <div id="bloco-docente" class="d-none">
                        <h2 class="h5 fw-bold text-dark mb-3">
                            Dados do Docente
                        </h2>

                        <div class="row g-3">

                            <div class="col-md-4">
                                <label for="titulacao" class="form-label fw-semibold">Titulação</label>
                                <input type="text" id="titulacao" name="titulacao" class="form-control"
                                    value="{{ old('titulacao', $docenteProfile->titulacao ?? 'Doutor(a)') }}">
                            </div>

                            <div class="col-md-4">
                                <label for="instituicao_sigla" class="form-label fw-semibold">Sigla da instituição</label>
                                <input type="text" id="instituicao_sigla" name="instituicao_sigla" class="form-control"
                                    value="{{ old('instituicao_sigla', $docenteProfile->instituicao_sigla ?? '') }}">
                            </div>

                            <div class="col-md-4">
                                <label for="instituicao_origem" class="form-label fw-semibold">Instituição de origem</label>
                                <input type="text" id="instituicao_origem" name="instituicao_origem" class="form-control"
                                    value="{{ old('instituicao_origem', $docenteProfile->instituicao_origem ?? '') }}">
                            </div>

                            <div class="col-md-6">
                                <label for="area_formacao" class="form-label fw-semibold">Área de formação</label>
                                <input type="text" id="area_formacao" name="area_formacao" class="form-control"
                                    value="{{ old('area_formacao', $docenteProfile->area_formacao ?? '') }}">
                            </div>

                            <div id="campo-categoria-docente" class="col-md-6">
                                <label for="categoria_docente" class="form-label fw-semibold">Categoria docente</label>
                                <select id="categoria_docente" name="categoria_docente" class="form-select">
                                    <option value="permanente" {{ old('categoria_docente', $docenteVinculo->categoria_docente ?? 'permanente') === 'permanente' ? 'selected' : '' }}>
                                        Permanente
                                    </option>
                                    <option value="colaborador" {{ old('categoria_docente', $docenteVinculo->categoria_docente ?? '') === 'colaborador' ? 'selected' : '' }}>
                                        Colaborador
                                    </option>
                                    <option value="visitante" {{ old('categoria_docente', $docenteVinculo->categoria_docente ?? '') === 'visitante' ? 'selected' : '' }}>
                                        Visitante
                                    </option>
                                </select>
                                <div class="form-text">
                                    Usado apenas para docentes internos.
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-check">
                                    <input type="hidden" name="vincular_programa" value="0">
                                    <input class="form-check-input" type="checkbox" name="vincular_programa" id="vincular_programa" value="1"
                                        {{ old('vincular_programa', $docenteVinculo ? true : false) ? 'checked' : '' }}>
                                    <span class="form-check-label">
                                        Vincular este docente ao programa da sessão
                                    </span>
                                </label>
                            </div>

                            @if ($programaAtual)
                            <div class="col-12 mt-2" id="bloco-coordenacao-docente">
                                <div class="card border-0 bg-light p-3 rounded-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-award-fill text-primary me-2 fs-5"></i>
                                        <h6 class="mb-0 fw-bold text-dark">Função de Coordenação no Programa</h6>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-2">Exclusivo: Permanente</span>
                                    </div>
                                    <p class="text-muted small mb-3">
                                        Apenas docentes do quadro permanente vinculados ao programa podem exercer a coordenação ou vice-coordenação. O programa admite apenas 1 coordenador(a) e 1 vice-coordenador(a). Ao atribuir a função a este docente, o titular anterior é automaticamente desmarcado e substituído. Desmarcar remove a titularidade. Um docente não pode acumular ambas as funções.
                                    </p>

                                    @error('coordenacao')
                                        <div class="alert alert-danger py-2 small mb-3">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $message }}
                                        </div>
                                    @enderror

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="p-2 bg-white rounded border">
                                                <div class="form-check form-switch mb-1">
                                                    <input type="hidden" name="eh_coordenador" value="0">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="eh_coordenador" name="eh_coordenador" value="1"
                                                        {{ old('eh_coordenador', $ehCoordenador) ? 'checked' : '' }}>
                                                    <label class="form-check-label fw-semibold" for="eh_coordenador">
                                                        Coordenador(a) do Programa
                                                    </label>
                                                </div>
                                                <div class="small text-muted ps-4">
                                                    @if ($coordenadorAtual && $coordenadorAtual->id !== $usuarioAtualId)
                                                        <span class="text-warning">
                                                            <i class="bi bi-info-circle me-1"></i>Atual: <strong>{{ $coordenadorAtual->name }}</strong> (será substituído ao salvar)
                                                        </span>
                                                    @elseif ($coordenadorAtual && $coordenadorAtual->id === $usuarioAtualId)
                                                        <span class="text-success">
                                                            <i class="bi bi-check-circle-fill me-1"></i>Este docente é o atual coordenador.
                                                        </span>
                                                    @else
                                                        <span class="text-muted">Nenhum coordenador atribuído no momento.</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="p-2 bg-white rounded border">
                                                <div class="form-check form-switch mb-1">
                                                    <input type="hidden" name="eh_vice_coordenador" value="0">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="eh_vice_coordenador" name="eh_vice_coordenador" value="1"
                                                        {{ old('eh_vice_coordenador', $ehViceCoordenador) ? 'checked' : '' }}>
                                                    <label class="form-check-label fw-semibold" for="eh_vice_coordenador">
                                                        Vice-Coordenador(a) do Programa
                                                    </label>
                                                </div>
                                                <div class="small text-muted ps-4">
                                                    @if ($viceCoordenadorAtual && $viceCoordenadorAtual->id !== $usuarioAtualId)
                                                        <span class="text-warning">
                                                            <i class="bi bi-info-circle me-1"></i>Atual: <strong>{{ $viceCoordenadorAtual->name }}</strong> (será substituído ao salvar)
                                                        </span>
                                                    @elseif ($viceCoordenadorAtual && $viceCoordenadorAtual->id === $usuarioAtualId)
                                                        <span class="text-success">
                                                            <i class="bi bi-check-circle-fill me-1"></i>Este docente é o atual vice-coordenador.
                                                        </span>
                                                    @else
                                                        <span class="text-muted">Nenhum vice-coordenador atribuído no momento.</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif

                        </div>
                    </div>

                    <div id="bloco-discente" class="d-none">
                        <h2 class="h5 fw-bold text-dark mb-3">
                            Dados do Discente
                        </h2>

                        <div class="row g-3">

                            <div class="col-md-6">
                                <label for="matricula" class="form-label fw-semibold">Matrícula</label>
                                <input type="text" id="matricula" name="matricula" class="form-control"
                                    value="{{ old('matricula', $discenteVinculo->matricula ?? '') }}">
                            </div>

                            <div class="col-md-6">
                                <label for="programa_curso_id" class="form-label fw-semibold">Curso</label>
                                <select id="programa_curso_id" name="programa_curso_id" class="form-select">
                                    <option value="">Selecione…</option>
                                    @foreach ($cursos as $curso)
                                        <option value="{{ $curso->id }}" @selected(old('programa_curso_id', $discenteVinculo->programa_curso_id ?? null) == $curso->id)>{{ $curso->nome }}{{ $curso->ativo ? '' : ' (inativo)' }}</option>
                                    @endforeach
                                </select>
                                @if ($cursos->isEmpty())
                                    <div class="form-text text-danger">Cadastre os cursos do programa antes de cadastrar discentes.</div>
                                @endif
                                @error('programa_curso_id')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-12">
                                <label for="linha_pesquisa_id" class="form-label fw-semibold">Linha de pesquisa</label>
                                <select id="linha_pesquisa_id" name="linha_pesquisa_id" class="form-select">
                                    <option value="">Selecione</option>

                                    @foreach ($linhasPesquisa as $linha)
                                    <option value="{{ $linha->id }}"
                                        {{ old('linha_pesquisa_id', $discenteVinculo->linha_pesquisa_id ?? '') == $linha->id ? 'selected' : '' }}>
                                        {{ $linha->nome }}
                                        —
                                        {{ $linha->areaConcentracao->nome }}
                                        —
                                        {{ $linha->areaConcentracao->programa->sigla ?? $linha->areaConcentracao->programa->nome }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="formacao" class="form-label fw-semibold">Formação</label>
                                <input type="text" id="formacao" name="formacao" class="form-control"
                                    value="{{ old('formacao', $discenteProfile->formacao ?? '') }}">
                            </div>

                            <div class="col-md-6">
                                <label for="instituicao_formacao" class="form-label fw-semibold">Instituição de formação</label>
                                <input type="text" id="instituicao_formacao" name="instituicao_formacao" class="form-control"
                                    value="{{ old('instituicao_formacao', $discenteProfile->instituicao_formacao ?? '') }}">
                            </div>

                            <div class="col-md-4">
                                <label for="pais" class="form-label fw-semibold">País</label>
                                <input type="text" id="pais" name="pais" class="form-control"
                                    value="{{ old('pais', $discenteProfile->pais ?? 'Brasil') }}">
                            </div>

                            <div class="col-md-4">
                                <label for="estado" class="form-label fw-semibold">Estado/Província</label>
                                <input type="text" id="estado" name="estado" maxlength="100" class="form-control"
                                    value="{{ old('estado', $discenteProfile->estado ?? '') }}"
                                    placeholder="Ex.: BA, São Paulo, Córdoba">
                                <div class="form-text">
                                    Opcional para países que não usam estado ou província.
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="cidade" class="form-label fw-semibold">Cidade</label>
                                <input type="text" id="cidade" name="cidade" class="form-control"
                                    value="{{ old('cidade', $discenteProfile->cidade ?? '') }}">
                            </div>

                            <div class="col-md-6">
                                <label for="data_ingresso" class="form-label fw-semibold">Data de ingresso</label>
                                <input type="text" id="data_ingresso" name="data_ingresso" class="form-control"
                                    value="{{ old('data_ingresso', $discenteVinculo?->data_ingresso ? \Carbon\Carbon::parse($discenteVinculo->data_ingresso)->format('d/m/Y') : '') }}"
                                    placeholder="dd/mm/aaaa"
                                    maxlength="10"
                                    inputmode="numeric">
                            </div>

                        </div>
                    </div>

                    <hr class="my-4">

                    <h2 class="h5 fw-bold text-dark mb-3">
                        Dados Complementares
                    </h2>

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="lattes_url" class="form-label fw-semibold">Currículo Lattes <small class="text-muted fw-normal">(obrigatório para discente)</small></label>
                            <input type="text" id="lattes_url" name="lattes_url" class="form-control"
                                value="{{ old('lattes_url', $docenteProfile->lattes_url ?? $discenteProfile->lattes_url ?? '') }}"
                                placeholder="http://lattes.cnpq.br/0000000000000000">
                        </div>

                        <div id="campo-orcid" class="col-md-6">
                            <label for="orcid_url" class="form-label fw-semibold">ORCID <small class="text-muted fw-normal">(obrigatório para docente; o discente pode informar no próprio cadastro)</small></label>
                            <input type="text" id="orcid_url" name="orcid_url" class="form-control"
                                value="{{ old('orcid_url', $docenteProfile->orcid_url ?? $discenteProfile->orcid_url ?? '') }}"
                                placeholder="https://orcid.org/0000-0000-0000-0000">
                            @error('orcid_url')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="telefone" class="form-label fw-semibold">Telefone</label>
                            <input type="text" id="telefone" name="telefone" class="form-control"
                                value="{{ old('telefone', $docenteProfile->telefone ?? $discenteProfile->telefone ?? '+55 ') }}"
                                placeholder="+55 (71) 99999-9999">
                            <div class="form-text">
                                Inclua o código do país. Padrão Brasil: +55.
                            </div>
                        </div>

                        <div class="col-md-6 d-flex align-items-end">
                            <label class="form-check mb-2">
                                <input type="hidden" name="telefone_whatsapp" value="0">
                                <input class="form-check-input" type="checkbox" name="telefone_whatsapp" value="1"
                                    {{ old('telefone_whatsapp', $docenteProfile->telefone_whatsapp ?? $discenteProfile->telefone_whatsapp ?? false) ? 'checked' : '' }}>
                                <span class="form-check-label">
                                    Este número também é WhatsApp
                                </span>
                            </label>
                        </div>

                    </div>

                    <div class="alert alert-warning mt-4 mb-0">
                        Cadastros incompletos serão mantidos como <strong>pendentes</strong>.
                        Quando um cadastro pendente for completado, o sistema enviará o acesso por e-mail.
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">
                            Cancelar
                        </a>

                        <button type="submit"
                            class="btn text-white fw-semibold"
                            style="background-color: #00303A;">
                            Atualizar usuário
                        </button>
                    </div>

                </form>

            </div>
        </div>

        @if ($trabalhoOrientacao)
            @include('usuarios.partials.orientacao')
        @endif

    </div>

    <script>
        function atualizarBlocosUsuario() {
            const categoria = document.getElementById('categoria').value;
            const tipoVinculo = document.getElementById('tipo_vinculo').value;
            const categoriaDocente = document.getElementById('categoria_docente')?.value;
            const vincularCheck = document.getElementById('vincular_programa');
            const blocoCoord = document.getElementById('bloco-coordenacao-docente');
            const coordCheck = document.getElementById('eh_coordenador');
            const viceCoordCheck = document.getElementById('eh_vice_coordenador');

            document.getElementById('campo-tipo-vinculo').classList.toggle('d-none', categoria !== 'docente');
            document.getElementById('bloco-docente').classList.toggle('d-none', categoria !== 'docente');
            document.getElementById('bloco-discente').classList.toggle('d-none', categoria !== 'discente');

            document.getElementById('campo-categoria-docente').classList.toggle(
                'd-none',
                categoria !== 'docente' || tipoVinculo !== 'interno'
            );

            // Apenas docentes internos do quadro permanente vinculados ao programa podem exercer coordenação
            const podeSerCoordenador = categoria === 'docente'
                && tipoVinculo === 'interno'
                && categoriaDocente === 'permanente'
                && (!vincularCheck || vincularCheck.checked);

            if (blocoCoord) {
                blocoCoord.classList.toggle('d-none', !podeSerCoordenador);
                if (!podeSerCoordenador) {
                    if (coordCheck) coordCheck.checked = false;
                    if (viceCoordCheck) viceCoordCheck.checked = false;
                }
            }

            document.getElementById('campo-orcid').classList.toggle('d-none', categoria === 'sec');
            document.getElementById('orcid_url').required = categoria === 'docente';
            document.getElementById('lattes_url').required = categoria === 'discente';
        }

        document.getElementById('categoria').addEventListener('change', atualizarBlocosUsuario);
        document.getElementById('tipo_vinculo').addEventListener('change', atualizarBlocosUsuario);
        document.getElementById('categoria_docente')?.addEventListener('change', atualizarBlocosUsuario);
        document.getElementById('vincular_programa')?.addEventListener('change', atualizarBlocosUsuario);

        const coordCheck = document.getElementById('eh_coordenador');
        const viceCoordCheck = document.getElementById('eh_vice_coordenador');

        if (coordCheck && viceCoordCheck) {
            coordCheck.addEventListener('change', function() {
                if (this.checked) {
                    viceCoordCheck.checked = false;
                }
            });

            viceCoordCheck.addEventListener('change', function() {
                if (this.checked) {
                    coordCheck.checked = false;
                }
            });
        }

        const telefoneInput = document.getElementById('telefone');

        telefoneInput.addEventListener('input', function() {
            let valor = telefoneInput.value.replace(/\D/g, '');

            if (valor === '') {
                telefoneInput.value = '+55 ';
                return;
            }

            if (!valor.startsWith('55')) {
                valor = '55' + valor;
            }

            valor = valor.substring(0, 13);

            const pais = valor.substring(0, 2);
            const ddd = valor.substring(2, 4);
            const parte1 = valor.length > 10 ?
                valor.substring(4, 9) :
                valor.substring(4, 8);
            const parte2 = valor.length > 10 ?
                valor.substring(9, 13) :
                valor.substring(8, 12);

            let formatado = '+' + pais;

            if (ddd) {
                formatado += ' (' + ddd + ')';
            }

            if (parte1) {
                formatado += ' ' + parte1;
            }

            if (parte2) {
                formatado += '-' + parte2;
            }

            telefoneInput.value = formatado;
        });

        const dataIngressoInput = document.getElementById('data_ingresso');

        dataIngressoInput.addEventListener('input', function() {
            let valor = dataIngressoInput.value.replace(/\D/g, '');

            valor = valor.substring(0, 8);

            if (valor.length >= 5) {
                valor = valor.replace(/(\d{2})(\d{2})(\d{1,4})/, '$1/$2/$3');
            } else if (valor.length >= 3) {
                valor = valor.replace(/(\d{2})(\d{1,2})/, '$1/$2');
            }

            dataIngressoInput.value = valor;
        });

        atualizarBlocosUsuario();
    </script>
</x-app-layout>