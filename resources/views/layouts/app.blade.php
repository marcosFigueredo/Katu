<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Katu — Autoavaliação e Qualidade de TCC</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('katu-icon-16.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('katu-icon-32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('katu-icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('katu-icon-192.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">


    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-gray-100">
    @php
        $secaoAtual = 'Dashboard';
        if (request()->routeIs('instituicao.*'))                              $secaoAtual = 'Instituição';
        elseif (request()->routeIs('programas.*', 'areas-concentracao.*', 'areas.linhas.*', 'linhas-pesquisa.*')) $secaoAtual = 'Programas';
        elseif (request()->routeIs('usuarios.*'))                             $secaoAtual = 'Usuários';
        elseif (request()->routeIs('bancas.*'))                               $secaoAtual = 'Bancas';
        elseif (request()->routeIs('baremas.*', 'barema-secoes.*', 'barema-itens.*')) $secaoAtual = 'Baremas';
        elseif (request()->routeIs('meu-trabalho.*', 'trabalho-impacto.*'))    $secaoAtual = 'Meu Trabalho';
        elseif (request()->routeIs('orientacao-avaliacao.*'))                 $secaoAtual = 'Avaliação da Orientação';
        elseif (request()->routeIs('avaliacoes.*'))                           $secaoAtual = 'Avaliações';
        elseif (request()->routeIs('swara.*'))                               $secaoAtual = 'SWARA';
        elseif (request()->routeIs('mabac.*'))                               $secaoAtual = 'MABAC';
        elseif (request()->routeIs('bibliometria.*'))                        $secaoAtual = 'Bibliometria';
        elseif (request()->routeIs('autoavaliacao.*'))                       $secaoAtual = 'Autoavaliação';
        elseif (request()->routeIs('profile.*'))                             $secaoAtual = 'Perfil do Usuário';
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
                        alt="Katu"
                        style="height: 64px; max-height: 64px; width: auto; object-fit: contain;">
                </div>

                {{-- Título central --}}
                <div style="display: flex; align-items: center; justify-content: center; height: 80px; text-align: center;">
                    <span style="color: white; font-weight: 700; font-size: 22px; line-height: 1;">
                        Katu — Autoavaliação e Qualidade de TCC
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

                            <form method="POST" action="{{ route('logout') }}" data-no-dirty-check>
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

                {{-- Relógio de sessão --}}
                <div class="px-4 py-2 border-b border-gray-100">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <div class="d-flex align-items-center gap-1" style="font-size: 11px; color: #6b7280;">
                            <i class="bi bi-clock" id="session-clock-icon"></i>
                            <span>Sessão expira em</span>
                        </div>
                        <span id="session-countdown"
                              class="fw-bold"
                              style="font-size: 13px; font-family: monospace; color: #00303A;">
                            30:00
                        </span>
                    </div>
                    <div class="progress" style="height: 3px; background: #e5e7eb;">
                        <div id="session-progress"
                             class="progress-bar"
                             style="width: 100%; background: #00303A; transition: width 1s linear;">
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
                        $configAberto = request()->routeIs('instituicao.*', 'instituicoes.*', 'programas.*', 'programa-cursos.*', 'programa-template.*', 'criterios-cpa.*', 'areas-concentracao.*', 'areas.linhas.*', 'linhas-pesquisa.*');
                        $usuariosAberto = request()->routeIs('usuarios.*');
                        $bancasAberto = request()->routeIs('bancas.*', 'baremas.*', 'barema-secoes.*', 'barema-itens.*', 'swara.*');
                        $avaliaAberto = request()->routeIs('mabac.*', 'bibliometria.*', 'autoavaliacao.*', 'orientacao-avaliacao.*');
                        $relatoriosAberto = request()->routeIs('relatorios.*');
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
                                      {{ request()->routeIs('programas.index', 'programas.create', 'programas.edit') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-diagram-3-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Programa
                            </a>

                            <a href="{{ route('programa-cursos.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('programa-cursos.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-mortarboard-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Cursos
                            </a>

                            <a href="{{ route('programa-template.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('programa-template.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-file-earmark-zip" style="font-size: 15px; flex-shrink:0;"></i>
                                Template LaTeX
                            </a>

                            <a href="{{ route('criterios-cpa.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('criterios-cpa.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-list-check" style="font-size: 15px; flex-shrink:0;"></i>
                                Critérios CPA
                            </a>

                            <a href="{{ route('tabelas-lattes.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('tabelas-lattes.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-diagram-2" style="font-size: 15px; flex-shrink:0;"></i>
                                Áreas CNPq e CNAE
                            </a>

                            @php($programaMenu = app(\App\Services\ContextoSecretaria::class)->programa(auth()->user()))
                            @if ($programaMenu)
                                <a href="{{ route('programas.areas.index', $programaMenu) }}"
                                   class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                          {{ request()->routeIs('programas.areas.*', 'areas-concentracao.*', 'areas.linhas.*', 'linhas-pesquisa.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                    <i class="bi bi-bookmarks-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                    Áreas e linhas de pesquisa
                                </a>
                            @endif

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

                            <a href="{{ route('usuarios.create') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('usuarios.create') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-person-plus-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Novo usuário
                            </a>

                            <a href="{{ route('usuarios.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('usuarios.index') && (!request()->filled('classe') || request('classe') === 'todos') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-people-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Todos os usuários
                            </a>

                            <a href="{{ route('usuarios.index', ['classe' => 'docentes']) }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('usuarios.index') && request('classe') === 'docentes' ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-person-badge-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Docentes
                            </a>

                            <a href="{{ route('usuarios.index', ['classe' => 'discentes']) }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('usuarios.index') && request('classe') === 'discentes' ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-mortarboard-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Discentes
                            </a>

                            <a href="{{ route('usuarios.index', ['classe' => 'externos']) }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('usuarios.index') && request('classe') === 'externos' ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-globe2" style="font-size: 15px; flex-shrink:0;"></i>
                                Docentes externos
                            </a>

                            <a href="{{ route('usuarios.index', ['classe' => 'coordenador']) }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('usuarios.index') && request('classe') === 'coordenador' ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-award-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Coordenador
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


                            <a href="{{ route('baremas.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('baremas.*', 'barema-secoes.*', 'barema-itens.*', 'swara.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-clipboard2-check-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Baremas
                            </a>

                        </div>
                    </div>

                    {{-- AVALIA KATU --}}
                    <div>
                        <button type="button"
                                class="w-100 flex items-center justify-between gap-2 px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100 border-0 bg-transparent"
                                data-bs-toggle="collapse"
                                data-bs-target="#menu-avalia"
                                aria-expanded="{{ $avaliaAberto ? 'true' : 'false' }}"
                                aria-controls="menu-avalia">
                            <span class="flex items-center gap-2">
                                <i class="bi bi-graph-up-arrow" style="font-size: 15px; flex-shrink:0;"></i>
                                Avalia Katu
                            </span>
                            <i class="bi bi-chevron-down small"></i>
                        </button>

                        <div id="menu-avalia" class="collapse {{ $avaliaAberto ? 'show' : '' }} mt-1 ms-3 space-y-1">

                            <a href="{{ route('mabac.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('mabac.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-bar-chart-steps" style="font-size: 15px; flex-shrink:0;"></i>
                                MABAC
                            </a>

                            <a href="{{ route('autoavaliacao.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('autoavaliacao.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-clipboard2-data" style="font-size: 15px; flex-shrink:0;"></i>
                                Autoavaliação
                            </a>

                            <a href="{{ route('orientacao-avaliacao.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('orientacao-avaliacao.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-person-heart" style="font-size: 15px; flex-shrink:0;"></i>
                                IEO
                            </a>

                        </div>
                    </div>

                    {{-- RELATÓRIOS --}}
                    <div>
                        <button type="button"
                                class="w-100 flex items-center justify-between gap-2 px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100 border-0 bg-transparent"
                                data-bs-toggle="collapse"
                                data-bs-target="#menu-relatorios"
                                aria-expanded="{{ $relatoriosAberto ? 'true' : 'false' }}"
                                aria-controls="menu-relatorios">
                            <span class="flex items-center gap-2">
                                <i class="bi bi-file-bar-graph-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Relatórios
                            </span>
                            <i class="bi bi-chevron-down small"></i>
                        </button>

                        <div id="menu-relatorios" class="collapse {{ $relatoriosAberto ? 'show' : '' }} mt-1 ms-3 space-y-1">

                            <a href="{{ route('relatorios.dashboard') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('relatorios.dashboard') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-speedometer2" style="font-size: 15px; flex-shrink:0;"></i>
                                Dashboard
                            </a>

                            <a href="{{ route('relatorios.bancas') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('relatorios.bancas') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-bar-chart-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Desempenho
                            </a>

                            <a href="{{ route('relatorios.composicao') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('relatorios.composicao') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-shield-check" style="font-size: 15px; flex-shrink:0;"></i>
                                Composição B1–B8
                            </a>

                            <a href="{{ route('relatorios.docentes') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('relatorios.docentes') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-person-badge" style="font-size: 15px; flex-shrink:0;"></i>
                                Bibliometria Docentes
                            </a>

                            <a href="{{ route('relatorios.percurso') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('relatorios.percurso') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-clock-history" style="font-size: 15px; flex-shrink:0;"></i>
                                Percurso
                            </a>

                            <a href="{{ route('relatorios.orientacao') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('relatorios.orientacao') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-award-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                IEO
                            </a>

                            <a href="{{ route('relatorios.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('relatorios.index') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-grid-fill" style="font-size: 15px; flex-shrink:0;"></i>
                                Ver todos
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
            Katu - Autoavaliação e Qualidade de TCC
        </footer>

    </div>


    {{-- Modal: aviso de sessão expirando --}}
    <div class="modal fade" id="modalSessaoExpirando" tabindex="-1"
         data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-body text-center py-4 px-4">
                    <div class="mb-3" style="font-size: 2.5rem; color: #e07b39;">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <h6 class="fw-bold mb-1" style="color:#00303A;">Sua sessão vai expirar</h6>
                    <p class="text-muted small mb-3">
                        Você será desconectado em
                        <span id="modal-countdown" class="fw-bold" style="color:#c03a2b; font-family:monospace;">05:00</span>
                        por inatividade.
                    </p>
                    <button type="button"
                            class="btn btn-primary w-100 fw-semibold"
                            onclick="katuSession.reset()">
                        <i class="bi bi-hand-thumbs-up-fill me-2"></i>Estou aqui!
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Formulário oculto para logout --}}
    <form id="form-logout-automatico" method="POST" action="{{ route('logout') }}" style="display:none;" data-no-dirty-check>
        @csrf
    </form>

    <script>
    const katuSession = (() => {
        const TOTAL     = 30 * 60;       // 30 minutos em segundos
        const AVISO_EM  = 5  * 60;       // mostrar aviso nos últimos 5 min
        let restante    = TOTAL;
        let modal       = null;
        let modalAberto = false;

        const elContador  = document.getElementById('session-countdown');
        const elProgress  = document.getElementById('session-progress');
        const elIcon      = document.getElementById('session-clock-icon');
        const elModal     = document.getElementById('modal-countdown');

        function fmt(s) {
            const m = String(Math.floor(s / 60)).padStart(2, '0');
            const r = String(s % 60).padStart(2, '0');
            return `${m}:${r}`;
        }

        function atualizar() {
            const pct = (restante / TOTAL) * 100;

            elContador.textContent = fmt(restante);
            elProgress.style.width = pct + '%';

            // Cor do relógio conforme urgência
            if (restante <= AVISO_EM) {
                elContador.style.color = restante <= 60 ? '#c03a2b' : '#e07b39';
                elProgress.style.background = restante <= 60 ? '#c03a2b' : '#e07b39';
                elIcon.className = 'bi bi-hourglass-split';
                elIcon.style.color = elContador.style.color;
            } else {
                elContador.style.color = '#00303A';
                elProgress.style.background = '#00303A';
                elIcon.className = 'bi bi-clock';
                elIcon.style.color = '#6b7280';
            }

            // Modal de aviso
            if (restante <= AVISO_EM && !modalAberto) {
                modal = modal || new bootstrap.Modal(document.getElementById('modalSessaoExpirando'));
                modal.show();
                modalAberto = true;
            }

            if (modalAberto && elModal) {
                elModal.textContent = fmt(restante);
            }

            if (restante <= 0) {
                document.getElementById('form-logout-automatico').submit();
                return;
            }

            restante--;
            setTimeout(atualizar, 1000);
        }

        function reset() {
            restante    = TOTAL;
            modalAberto = false;
            if (modal) modal.hide();
        }

        // Reinicia o timer em qualquer atividade do usuário
        const eventos = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];
        eventos.forEach(ev => document.addEventListener(ev, () => { reset(); }, { passive: true }));

        // Inicia
        atualizar();

        return { reset };
    })();
    </script>
</body>

</html>
