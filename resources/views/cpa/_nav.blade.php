{{-- Navegação da área da CPA. --}}
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('cpa.index') ? 'active' : '' }}" href="{{ route('cpa.index') }}">Qualidade</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('cpa.periodos.*', 'cpa.pesos.*') ? 'active' : '' }}" href="{{ route('cpa.periodos.index') }}">Faixas e pesos</a></li>
</ul>
