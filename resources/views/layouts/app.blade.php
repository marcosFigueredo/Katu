<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Sistema de Avaliação de Bancas</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">


    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-gray-100">
    @php
        $secaoAtual = 'Dashboard';
        if (request()->routeIs('instituicao.*'))                              $secaoAtual = 'Instituição';
        elseif (request()->routeIs('programas.*', 'areas-concentracao.*', 'areas.linhas.*', 'linhas-pesquisa.*')) $secaoAtual = 'Programas';
        elseif (request()->routeIs('usuarios.*'))                             $secaoAtual = 'Usuários';
        elseif (request()->routeIs('orientacoes.*'))                          $secaoAtual = 'Orientações';
        elseif (request()->routeIs('bancas.*'))                               $secaoAtual = 'Bancas';
        elseif (request()->routeIs('baremas.*', 'barema-secoes.*', 'barema-itens.*')) $secaoAtual = 'Baremas';
        elseif (request()->routeIs('meu-trabalho.*'))                         $secaoAtual = 'Meu Trabalho';
        elseif (request()->routeIs('avaliacoes.*'))                           $secaoAtual = 'Avaliações';
        elseif (request()->routeIs('profile.*'))                              $secaoAtual = 'Perfil do Usuário';
    @endphp

    <div class="min-h-screen flex flex-col">

        {{-- Faixa superior --}}
        <header style="height: 80px; background-color: #00303A; box-shadow: 0 2px 6px rgba(0,0,0,0.25);">
            <div style="
        height: 80px;
        display: grid;
        grid-template-columns: 260px 1fr 260px;
        align-items: center;
        padding: 0 24px;
    ">

                {{-- Logo à esquerda --}}
                <div style="display: flex; align-items: center; height: 80px;">
                    <img src="{{ asset('images/logoBranco.png') }}"
                        alt="PPGMSB"
                        style="height: 64px; max-height: 64px; width: auto; object-fit: contain;">
                </div>

                {{-- Título central --}}
                <div style="display: flex; align-items: center; justify-content: center; height: 80px; text-align: center;">
                    <span style="color: white; font-weight: 700; font-size: 22px; line-height: 1;">
                        Sistema de Avaliação de Bancas
                    </span>
                </div>

                {{-- Usuário à direita --}}
                <div style="display: flex; align-items: center; justify-content: flex-end; height: 80px;">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button type="button"
                                style="
                            display: flex;
                            align-items: center;
                            gap: 10px;
                            color: white;
                            background: transparent;
                            border: none;
                            cursor: pointer;
                        ">

                                <span style="font-size: 14px; font-weight: 600;">
                                    {{ Auth::user()->name }}
                                </span>

                                <svg xmlns="http://www.w3.org/2000/svg"
                                    style="height: 36px; width: 36px;"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.5">
                                    <path stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="px-4 py-2 text-sm text-gray-700 border-b">
                                {{ Auth::user()->name }}
                            </div>

                            <x-dropdown-link :href="route('profile.edit')">
                                Perfil
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                    Sair
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>

            </div>
        </header>

        <div class="flex flex-1">

            {{-- Menu lateral --}}
            <aside style="width: 280px; background: white; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column;">

                <div class="px-6 py-5 border-b border-gray-200">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle border d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width: 52px; height: 52px; border-color: #00303A !important;">
                            <img src="{{ asset('images/logoApp.png') }}"
                                 alt="Sistema de Avaliação de Bancas"
                                 style="width: 38px; height: 38px; object-fit: contain;">
                        </div>
                        <div style="min-width: 0;">
                            <div class="fw-bold text-truncate" style="color:#00303A; font-size: 16px;">
                                {{ Auth::user()->name }}
                            </div>
                            <div class="d-flex align-items-center gap-2 text-muted small">
                                <span class="rounded-circle d-inline-block" style="width: 10px; height: 10px; background:#16a34a;"></span>
                                {{ match(Auth::user()->categoria) {
                                    'sec' => 'Secretaria',
                                    'docente' => 'Docente',
                                    'discente' => 'Discente',
                                    default => ucfirst(Auth::user()->categoria ?? 'Usuário'),
                                } }}
                            </div>
                        </div>
                    </div>
                </div>

                <nav class="flex-1 p-4 space-y-4">

                    <div>
                        <a href="{{ route('dashboard') }}"
                           class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                  {{ request()->routeIs('dashboard') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                            <i class="bi bi-grid-1x2-fill" style="font-size: 15px; flex-shrink:0;"></i>
                            Dashboard
                        </a>
                    </div>

                    @if (auth()->user()?->categoria === 'discente')
                    <div>
                        <a href="{{ route('meu-trabalho.index') }}"
                           class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                  {{ request()->routeIs('meu-trabalho.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                            <i class="bi bi-file-earmark-text-fill" style="font-size: 15px; flex-shrink:0;"></i>
                            Meu Trabalho
                        </a>
                    </div>
                    @endif

                    @if (auth()->user()?->categoria === 'docente')
                    <div>
                        <a href="{{ route('avaliacoes.index') }}"
                           class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                  {{ request()->routeIs('avaliacoes.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                            <i class="bi bi-clipboard2-check-fill" style="font-size: 15px; flex-shrink:0;"></i>
                            Avaliações
                        </a>
                    </div>
                    @endif

                    @if (auth()->user()?->categoria === 'sec')
                    @php
                        $configAberto = request()->routeIs('instituicao.*', 'programas.*', 'areas-concentracao.*', 'areas.linhas.*', 'linhas-pesquisa.*');
                        $usuariosAberto = request()->routeIs('usuarios.*');
                        $bancasAberto = request()->routeIs('bancas.*', 'orientacoes.*', 'baremas.*', 'barema-secoes.*', 'barema-itens.*');
                    @endphp

                    {{-- CONFIGURAÇÕES --}}
                    <div>
                        <button type="button"
                                class="w-100 flex items-center justify-between gap-2 px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100 border-0 bg-transparent"
                                data-bs-toggle="collapse"
                                data-bs-target="#menu-configuracoes"
                                aria-expanded="{{ $configAberto ? 'true' : 'false' }}"
                                aria-controls="menu-configuracoes">
                            <span class="flex items-center gap-2">
                                <i class="bi bi-gear-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Configurações
                            </span>
                            <i class="bi bi-chevron-down small"></i>
                        </button>

                        <div id="menu-configuracoes" class="collapse {{ $configAberto ? 'show' : '' }} mt-1 ms-3 space-y-1">

                            <a href="{{ route('instituicao.edit') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('instituicao.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-bank2" style="font-size: 15px; flex-shrink:0;"></i>
                                Instituição
                            </a>

                            <a href="{{ route('programas.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('programas.*', 'areas-concentracao.*', 'areas.linhas.*', 'linhas-pesquisa.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-diagram-3-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Programas
                            </a>

                        </div>
                    </div>

                    {{-- USUÁRIOS --}}
                    <div>
                        <button type="button"
                                class="w-100 flex items-center justify-between gap-2 px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100 border-0 bg-transparent"
                                data-bs-toggle="collapse"
                                data-bs-target="#menu-usuarios"
                                aria-expanded="{{ $usuariosAberto ? 'true' : 'false' }}"
                                aria-controls="menu-usuarios">
                            <span class="flex items-center gap-2">
                                <i class="bi bi-people-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Usuários
                            </span>
                            <i class="bi bi-chevron-down small"></i>
                        </button>

                        <div id="menu-usuarios" class="collapse {{ $usuariosAberto ? 'show' : '' }} mt-1 ms-3 space-y-1">

                            <a href="{{ route('usuarios.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('usuarios.index', 'usuarios.edit', 'usuarios.show') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-people-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Listar usuários
                            </a>

                            <a href="{{ route('usuarios.create') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('usuarios.create') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-person-plus-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Novo usuário
                            </a>

                        </div>
                    </div>

                    {{-- BANCAS --}}
                    <div>
                        <button type="button"
                                class="w-100 flex items-center justify-between gap-2 px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100 border-0 bg-transparent"
                                data-bs-toggle="collapse"
                                data-bs-target="#menu-bancas"
                                aria-expanded="{{ $bancasAberto ? 'true' : 'false' }}"
                                aria-controls="menu-bancas">
                            <span class="flex items-center gap-2">
                                <i class="bi bi-person-workspace" style="font-size: 15px; flex-shrink:0;"></i>
                                Banca
                            </span>
                            <i class="bi bi-chevron-down small"></i>
                        </button>

                        <div id="menu-bancas" class="collapse {{ $bancasAberto ? 'show' : '' }} mt-1 ms-3 space-y-1">

                            <a href="{{ route('bancas.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('bancas.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-person-workspace" style="font-size: 15px; flex-shrink:0;"></i>
                                Bancas
                            </a>

                            <a href="{{ route('orientacoes.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('orientacoes.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-arrow-left-right" style="font-size: 15px; flex-shrink:0;"></i>
                                Orientações
                            </a>

                            <a href="{{ route('baremas.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('baremas.*', 'barema-secoes.*', 'barema-itens.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-clipboard2-check-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Barema
                            </a>

                        </div>
                    </div>

                    @endif

                </nav>
            </aside>

            {{-- Conteúdo principal --}}
            <main class="flex-1">
                <div class="p-6">
                    {{ $slot }}
                </div>
            </main>
        </div>

        {{-- Rodapé --}}
        <footer class="py-3 text-center text-sm text-white"
            style="background-color: #00303A;">
            PPGMSB - Sistema de Avaliação de Bancas
        </footer>

    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
