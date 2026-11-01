{{-- Selo da classe de qualidade (1 = alta … 5 = baixa). --}}
@php
    $cores = [1 => 'success', 2 => 'primary', 3 => 'info', 4 => 'warning', 5 => 'danger'];
@endphp
@if ($classe)
    <span class="badge text-bg-{{ $cores[$classe] }}">{{ $classe }}. {{ \App\Services\IndiceQualidadeService::CLASSES[$classe] }}</span>
@else
    <span class="badge text-bg-light">Sem índice</span>
@endif
