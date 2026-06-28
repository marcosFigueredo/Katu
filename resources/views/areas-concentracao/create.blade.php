<x-app-layout>
    <div class="max-w-4xl mx-auto">

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">
                Nova Área de Concentração
            </h1>

            <p class="text-sm text-gray-600 mt-1">
                Programa: <strong>{{ $programa->nome }}</strong>
            </p>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6">

                <form method="POST" action="{{ route('programas.areas.store', $programa) }}">
                    @csrf

                    <div class="space-y-4">

                        <div>
                            <x-input-label for="nome" value="Nome da área de concentração" />

                            <x-text-input
                                id="nome"
                                name="nome"
                                type="text"
                                class="mt-1 block w-full"
                                value="{{ old('nome') }}"
                                required
                            />

                            <x-input-error :messages="$errors->get('nome')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="objetivo" value="Objetivo da área de concentração" />

                            <textarea
                                id="objetivo"
                                name="objetivo"
                                rows="5"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >{{ old('objetivo') }}</textarea>

                            <x-input-error :messages="$errors->get('objetivo')" class="mt-2" />
                        </div>

                    </div>

                    <div class="flex justify-between mt-6">
                        <a href="{{ route('programas.areas.index', $programa) }}"
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md text-sm font-semibold hover:bg-gray-300">
                            Cancelar
                        </a>

                        <button type="submit"
                            class="px-4 py-2 text-white rounded-md text-sm font-semibold"
                            style="background-color: #00303A;">
                            Salvar área
                        </button>
                    </div>
                </form>

            </div>
        </div>

    </div>
</x-app-layout>