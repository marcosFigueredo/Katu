<x-app-layout>
    <div class="max-w-4xl mx-auto">

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">
                Editar Programa
            </h1>

            <p class="text-sm text-gray-600 mt-1">
                Atualize os dados gerais do programa de pós-graduação.
            </p>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6">

                <form method="POST" action="{{ route('programas.update', $programa) }}">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div class="md:col-span-2">
                            <x-input-label for="nome" value="Nome do programa" />
                            <x-text-input id="nome" name="nome" type="text" class="mt-1 block w-full"
                                value="{{ old('nome', $programa->nome) }}" required />
                            <x-input-error :messages="$errors->get('nome')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="sigla" value="Sigla" />
                            <x-text-input id="sigla" name="sigla" type="text" class="mt-1 block w-full"
                                value="{{ old('sigla', $programa->sigla) }}" />
                            <x-input-error :messages="$errors->get('sigla')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="codigo_capes" value="Código CAPES" />
                            <x-text-input id="codigo_capes" name="codigo_capes" type="text" class="mt-1 block w-full"
                                value="{{ old('codigo_capes', $programa->codigo_capes) }}" />
                            <x-input-error :messages="$errors->get('codigo_capes')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="area_avaliacao" value="Área de avaliação" />
                            <x-text-input id="area_avaliacao" name="area_avaliacao" type="text" class="mt-1 block w-full"
                                value="{{ old('area_avaliacao', $programa->area_avaliacao) }}" />
                            <x-input-error :messages="$errors->get('area_avaliacao')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="email" value="E-mail" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                                value="{{ old('email', $programa->email) }}" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="telefone" value="Telefone" />
                            <x-text-input id="telefone" name="telefone" type="text" class="mt-1 block w-full"
                                value="{{ old('telefone', $programa->telefone) }}" />
                            <x-input-error :messages="$errors->get('telefone')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="site" value="Site" />
                            <x-text-input id="site" name="site" type="text" class="mt-1 block w-full"
                                value="{{ old('site', $programa->site) }}" />
                            <x-input-error :messages="$errors->get('site')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="descricao" value="Descrição" />
                            <textarea id="descricao" name="descricao" rows="4"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('descricao', $programa->descricao) }}</textarea>
                            <x-input-error :messages="$errors->get('descricao')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <div class="flex items-center gap-6 mt-2">
                                <label class="flex items-center">
                                    <input type="checkbox" name="oferta_mestrado" value="1"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        {{ old('oferta_mestrado', $programa->oferta_mestrado) ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-gray-700">Oferta Mestrado</span>
                                </label>

                                <label class="flex items-center">
                                    <input type="checkbox" name="oferta_doutorado" value="1"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        {{ old('oferta_doutorado', $programa->oferta_doutorado) ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-gray-700">Oferta Doutorado</span>
                                </label>
                            </div>
                        </div>

                    </div>

                    <div class="flex justify-between mt-6">
                        <a href="{{ route('programas.index') }}"
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md text-sm font-semibold hover:bg-gray-300">
                            Cancelar
                        </a>

                        <button type="submit"
                            class="px-4 py-2 text-white rounded-md text-sm font-semibold"
                            style="background-color: #00303A;">
                            Atualizar programa
                        </button>
                    </div>
                </form>

            </div>
        </div>

    </div>
</x-app-layout>