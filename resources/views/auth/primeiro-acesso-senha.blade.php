<x-guest-layout>
    <form method="POST" action="{{ route('primeiro-acesso-senha.update') }}">
        @csrf
        @method('PUT')

        <div class="mb-4">
            <h2 class="text-xl font-semibold text-gray-800">
                Alteração obrigatória de senha
            </h2>

            <p class="mt-2 text-sm text-gray-600">
                Este é seu primeiro acesso ao sistema. Para continuar, cadastre uma nova senha.
            </p>
        </div>

        <div>
            <x-input-label for="password" value="Nova senha" />

            <x-text-input
                id="password"
                class="block mt-1 w-full"
                type="password"
                name="password"
                required
                autofocus
                autocomplete="new-password"
            />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Confirmar nova senha" />

            <x-text-input
                id="password_confirmation"
                class="block mt-1 w-full"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
            />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-6">
            <x-primary-button>
                Alterar senha e continuar
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>