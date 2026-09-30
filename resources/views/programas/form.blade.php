@php
    $editando = (bool) $programa;
    $campoClasse = 'mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
@endphp
<x-app-layout>
    <div class="max-w-4xl mx-auto">

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">
                {{ $editando ? 'Programa' : 'Novo Programa' }}
            </h1>
            <p class="text-sm text-gray-600 mt-1">
                @if ($editando)
                    Atualize os dados do programa de pós-graduação administrado por esta Secretaria.
                @else
                    Cadastre o programa de pós-graduação administrado por esta Secretaria. Cada Secretaria administra um único programa.
                @endif
            </p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6">

                <form method="POST" enctype="multipart/form-data"
                      action="{{ $editando ? route('programas.update', $programa) : route('programas.store') }}">
                    @csrf
                    @if ($editando)
                        @method('PUT')
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div class="md:col-span-2">
                            <x-input-label for="nome" value="Nome do programa" />
                            <x-text-input id="nome" name="nome" type="text" class="mt-1 block w-full"
                                value="{{ old('nome', $programa?->nome) }}" required />
                            <x-input-error :messages="$errors->get('nome')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="instituicao_id" value="Instituição" />
                            <select id="instituicao_id" name="instituicao_id" class="{{ $campoClasse }}" required>
                                <option value="">Selecione…</option>
                                @foreach ($instituicoes as $inst)
                                    <option value="{{ $inst->id }}" @selected(old('instituicao_id', $programa?->instituicao_id) == $inst->id)>
                                        {{ $inst->sigla }} — {{ $inst->nome }}
                                    </option>
                                @endforeach
                            </select>
                            @unless ($editando)
                                <p class="text-xs text-gray-500 mt-1">
                                    Não encontrou? <a href="{{ route('instituicoes.create') }}" class="text-indigo-700 underline">Cadastrar nova instituição</a>.
                                </p>
                            @endunless
                            <x-input-error :messages="$errors->get('instituicao_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="sigla" value="Sigla" />
                            <x-text-input id="sigla" name="sigla" type="text" class="mt-1 block w-full"
                                value="{{ old('sigla', $programa?->sigla) }}" />
                            <x-input-error :messages="$errors->get('sigla')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="codigo_capes" value="Código CAPES" />
                            <x-text-input id="codigo_capes" name="codigo_capes" type="text" class="mt-1 block w-full"
                                value="{{ old('codigo_capes', $programa?->codigo_capes) }}" />
                            <x-input-error :messages="$errors->get('codigo_capes')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="area_avaliacao" value="Área de avaliação" />
                            <x-text-input id="area_avaliacao" name="area_avaliacao" type="text" class="mt-1 block w-full"
                                value="{{ old('area_avaliacao', $programa?->area_avaliacao) }}" />
                            <x-input-error :messages="$errors->get('area_avaliacao')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="conceito_capes" value="Conceito CAPES" />
                            <select id="conceito_capes" name="conceito_capes" class="{{ $campoClasse }}">
                                <option value="">—</option>
                                @foreach (range(1, 7) as $conceito)
                                    <option value="{{ $conceito }}" @selected(old('conceito_capes', $programa?->conceito_capes) == $conceito)>{{ $conceito }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('conceito_capes')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="ano_inicio" value="Ano de início" />
                            <x-text-input id="ano_inicio" name="ano_inicio" type="number" min="1950" max="{{ now()->year }}"
                                class="mt-1 block w-full" value="{{ old('ano_inicio', $programa?->ano_inicio) }}" />
                            <x-input-error :messages="$errors->get('ano_inicio')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="max_coorientadores" value="Máximo de coorientadores por discente" />
                            <x-text-input id="max_coorientadores" name="max_coorientadores" type="number" min="0" max="5"
                                class="mt-1 block w-full" value="{{ old('max_coorientadores', $programa?->max_coorientadores ?? 1) }}" />
                            <p class="text-xs text-gray-500 mt-1">Além do orientador principal. Coorientadores podem ser internos ou externos. Use 0 para não permitir.</p>
                            <x-input-error :messages="$errors->get('max_coorientadores')" class="mt-2" />
                        </div>

                        @if ($editando)
                            <div>
                                <x-input-label for="coordenador_id" value="Coordenador(a)" />
                                <select id="coordenador_id" name="coordenador_id" class="{{ $campoClasse }}">
                                    <option value="">—</option>
                                    @foreach ($docentesPermanentes as $docente)
                                        <option value="{{ $docente->id }}" @selected(old('coordenador_id', $programa->coordenador_id) == $docente->id)>{{ $docente->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('coordenador_id')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="vice_coordenador_id" value="Vice-coordenador(a)" />
                                <select id="vice_coordenador_id" name="vice_coordenador_id" class="{{ $campoClasse }}">
                                    <option value="">—</option>
                                    @foreach ($docentesPermanentes as $docente)
                                        <option value="{{ $docente->id }}" @selected(old('vice_coordenador_id', $programa->vice_coordenador_id) == $docente->id)>{{ $docente->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('vice_coordenador_id')" class="mt-2" />
                            </div>
                            @if ($docentesPermanentes->isEmpty())
                                <p class="md:col-span-2 text-xs text-gray-500">
                                    Para escolher a coordenação, vincule docentes permanentes ao programa.
                                </p>
                            @endif
                        @endif

                        <div>
                            <x-input-label for="email" value="E-mail" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                                value="{{ old('email', $programa?->email) }}" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="telefone" value="Telefone" />
                            <x-text-input id="telefone" name="telefone" type="text" class="mt-1 block w-full"
                                value="{{ old('telefone', $programa?->telefone) }}" />
                            <x-input-error :messages="$errors->get('telefone')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="site" value="Site" />
                            <x-text-input id="site" name="site" type="text" class="mt-1 block w-full"
                                value="{{ old('site', $programa?->site) }}" />
                            <x-input-error :messages="$errors->get('site')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="descricao" value="Descrição" />
                            <textarea id="descricao" name="descricao" rows="4" class="{{ $campoClasse }}">{{ old('descricao', $programa?->descricao) }}</textarea>
                            <x-input-error :messages="$errors->get('descricao')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="logo" value="Logotipo do programa (opcional — PNG, JPG ou WebP, até 2 MB)" />
                            <div class="flex items-center gap-4 mt-1">
                                <img id="logo-preview" src="{{ $programa?->logoUrl() }}" alt="Logotipo do programa"
                                     class="h-16 object-contain {{ $programa?->logoUrl() ? '' : 'hidden' }}">
                                <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"
                                       class="block w-full text-sm"
                                       onchange="if (this.files[0]) { const img = document.getElementById('logo-preview'); img.src = URL.createObjectURL(this.files[0]); img.classList.remove('hidden'); }">
                            </div>
                            <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                        </div>

                    </div>

                    <div class="flex justify-between mt-6">
                        <a href="{{ route('dashboard') }}"
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md text-sm font-semibold hover:bg-gray-300">
                            Cancelar
                        </a>

                        <button type="submit"
                            class="px-4 py-2 text-white rounded-md text-sm font-semibold"
                            style="background-color: #00303A;">
                            {{ $editando ? 'Atualizar programa' : 'Salvar programa' }}
                        </button>
                    </div>
                </form>

            </div>
        </div>

        @if ($editando)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6">
                <div class="p-6 flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Áreas e linhas de pesquisa</h2>
                        <p class="text-sm text-gray-600">
                            {{ $programa->areasConcentracao()->count() }} área(s) de concentração cadastrada(s). Cada área reúne suas linhas de pesquisa.
                        </p>
                    </div>
                    <a href="{{ route('programas.areas.index', $programa) }}"
                       class="px-4 py-2 text-white rounded-md text-sm font-semibold" style="background-color: #00303A;">
                        Gerenciar áreas e linhas
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-3"><h2 class="text-lg font-semibold text-gray-800">Cursos</h2><a href="{{ route('programa-cursos.index') }}" class="text-sm text-indigo-700 underline">Gerenciar cursos e regras de banca</a></div>
                    @forelse ($programa->cursos as $curso)
                        <div class="flex justify-between text-sm border-b py-2">
                            <span>{{ $curso->nome }}</span>
                            <span class="text-gray-600">
                                Qualificação até {{ $curso->prazo_qualificacao_meses }} meses · Defesa até {{ $curso->prazo_defesa_meses }} meses
                                · {{ $curso->ativo ? 'ativo' : 'inativo' }}
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-600">Nenhum curso cadastrado.</p>
                    @endforelse
                </div>
            </div>
        @endif

    </div>
</x-app-layout>
