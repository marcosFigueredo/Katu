<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatório Consolidado — Banca</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #1f2937; background: #fff; }

        .page { max-width: 900px; margin: 0 auto; padding: 32px 40px; }

        .header { border-bottom: 3px solid #00303A; padding-bottom: 16px; margin-bottom: 24px; }
        .header h1 { font-size: 18px; color: #00303A; margin-bottom: 4px; }
        .header p  { font-size: 12px; color: #6b7280; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px;
                     border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; }
        .info-item label { display: block; font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; }
        .info-item p { font-size: 13px; font-weight: 600; margin-top: 2px; }

        .resultado-box {
            padding: 12px 20px; border-radius: 6px; margin-bottom: 24px;
            display: flex; align-items: center; gap: 16px;
        }
        .resultado-aprovado { background: #f0fdf4; border: 2px solid #16a34a; }
        .resultado-modificacoes { background: #fefce8; border: 2px solid #ca8a04; }
        .resultado-reprovado { background: #fef2f2; border: 2px solid #dc2626; }
        .resultado-label { font-size: 11px; color: #6b7280; text-transform: uppercase; }
        .resultado-valor { font-size: 20px; font-weight: 700; }
        .resultado-aprovado .resultado-valor { color: #16a34a; }
        .resultado-modificacoes .resultado-valor { color: #ca8a04; }
        .resultado-reprovado .resultado-valor { color: #dc2626; }

        h2 { font-size: 15px; color: #00303A; margin: 24px 0 12px; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; }

        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 24px; }
        th { background: #00303A; color: #fff; padding: 8px 10px; text-align: left; font-weight: 600; }
        th.num { text-align: center; }
        td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        td.num { text-align: center; }
        tr:nth-child(even) td { background: #f9fafb; }
        .nota-cell { font-weight: 700; color: #00303A; font-size: 14px; }
        .sem-nota { color: #9ca3af; font-style: italic; }

        .media-row td { background: #f0f4ff !important; font-weight: 700; font-size: 13px; }

        .secao-header td { background: #e5e7eb !important; font-weight: 700; color: #374151; }

        .footer { margin-top: 40px; font-size: 11px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 12px; }

        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .page { padding: 20px; max-width: 100%; }
        }
    </style>
</head>
<body>

@php
    $discente   = $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user;
    $trabalho   = $banca->trabalhoAcademico;
    $tipoLabel  = $banca->tipo === 'qualificacao' ? 'Qualificação' : 'Defesa';
    $resultadoLabel = match($banca->resultado ?? '') {
        'aprovado'              => 'Aprovado',
        'aprovado_modificacoes' => 'Aprovado com modificações',
        'reprovado'             => 'Reprovado',
        default                 => null,
    };
    $resultadoClass = match($banca->resultado ?? '') {
        'aprovado'              => 'resultado-aprovado',
        'aprovado_modificacoes' => 'resultado-modificacoes',
        'reprovado'             => 'resultado-reprovado',
        default                 => '',
    };
@endphp

<div class="page">

    {{-- Botão imprimir --}}
    <div class="no-print" style="text-align:right; margin-bottom:16px;">
        <button onclick="window.print()"
                style="background:#00303A; color:#fff; border:none; padding:8px 20px; border-radius:6px; cursor:pointer; font-size:13px;">
            Imprimir / Salvar PDF
        </button>
        <a href="{{ route('avaliacoes.banca', $banca) }}"
           style="margin-left:8px; color:#6b7280; font-size:13px; text-decoration:none;">← Voltar</a>
    </div>

    {{-- Cabeçalho --}}
    <div class="header">
        <h1>PPGMSB — Relatório Consolidado de Banca de {{ $tipoLabel }}</h1>
        <p>{{ $trabalho?->programa?->nome ?? 'PPGMSB' }} — Universidade do Estado da Bahia (UNEB)</p>
    </div>

    {{-- Informações gerais --}}
    <div class="info-grid">
        <div class="info-item">
            <label>Discente</label>
            <p>{{ $discente?->name ?? '—' }}</p>
        </div>
        <div class="info-item">
            <label>Nível</label>
            <p>{{ $trabalho->nivel === 'doutorado' ? 'Doutorado' : 'Mestrado' }}</p>
        </div>
        @if ($trabalho?->titulo)
            <div class="info-item" style="grid-column: span 2;">
                <label>Título do trabalho</label>
                <p>{{ $trabalho->titulo }}</p>
            </div>
        @endif
        <div class="info-item">
            <label>Data da banca</label>
            <p>{{ $banca->data_hora?->format('d/m/Y \à\s H:i') ?? '—' }}</p>
        </div>
        @if ($banca->local)
            <div class="info-item">
                <label>Local</label>
                <p>{{ $banca->local }}</p>
            </div>
        @endif
    </div>

    {{-- Resultado --}}
    @if ($resultadoLabel)
        <div class="resultado-box {{ $resultadoClass }}">
            <div>
                <div class="resultado-label">Resultado</div>
                <div class="resultado-valor">{{ $resultadoLabel }}</div>
            </div>
            @if ($banca->nota_media !== null)
                <div style="margin-left:auto; text-align:right;">
                    <div class="resultado-label">Nota média consolidada</div>
                    <div class="resultado-valor">{{ number_format($banca->nota_media, 1, ',', '') }} / 10,0</div>
                </div>
            @endif
        </div>
    @endif

    {{-- Para cada barema --}}
    @foreach ($baremas as $barema)
        @php
            $confidencial = str_contains($barema->tipo, 'confidencial');
            $tituloBarema = ($confidencial ? 'Avaliação Confidencial' : 'Avaliação Padrão') . ' — ' . $barema->nome;
        @endphp

        <h2>
            @if ($confidencial)
                <span style="background:#1e1b4b; color:#fff; font-size:10px; padding:2px 8px; border-radius:3px; margin-right:6px;">CONF.</span>
            @endif
            {{ $tituloBarema }}
        </h2>

        <table>
            <thead>
                <tr>
                    <th style="width:40%;">Item</th>
                    @foreach ($avaliadores as $membro)
                        <th class="num" style="width:{{ intval(50 / $avaliadores->count()) }}%;">
                            {{ $membro->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '—' }}
                        </th>
                    @endforeach
                    @if ($avaliadores->count() > 1)
                        <th class="num">Média</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($barema->secoes as $secao)
                    <tr class="secao-header">
                        <td colspan="{{ $avaliadores->count() + ($avaliadores->count() > 1 ? 2 : 1) }}">
                            {{ $secao->nome }}
                            @if ($secao->peso != 1)
                                <span style="font-weight:400; font-size:11px; color:#6b7280;"> — Peso {{ number_format($secao->peso, 1) }}</span>
                            @endif
                        </td>
                    </tr>

                    @foreach ($secao->itens as $item)
                        <tr>
                            <td>
                                {{ $item->nome }}
                                <span style="color:#9ca3af; font-size:11px;">
                                    (máx {{ number_format($item->nota_maxima, 1, ',', '') }})
                                </span>
                            </td>
                            @php $notasItem = []; @endphp
                            @foreach ($avaliadores as $membro)
                                @php
                                    $av  = $membro->avaliacoes->firstWhere('barema_id', $barema->id);
                                    $nota = $av?->notas->firstWhere('barema_item_id', $item->id);
                                    $val = $nota?->nota;
                                    if ($val !== null) $notasItem[] = $val;
                                @endphp
                                <td class="num {{ $val !== null ? 'nota-cell' : 'sem-nota' }}">
                                    {{ $val !== null ? number_format($val, 1, ',', '') : '—' }}
                                </td>
                            @endforeach
                            @if ($avaliadores->count() > 1)
                                <td class="num nota-cell">
                                    {{ count($notasItem) > 0 ? number_format(array_sum($notasItem) / count($notasItem), 1, ',', '') : '—' }}
                                </td>
                            @endif
                        </tr>
                    @endforeach
                @endforeach

                {{-- Linha de nota final por avaliador --}}
                <tr class="media-row">
                    <td>Nota final (barema)</td>
                    @php $notasFinais = []; @endphp
                    @foreach ($avaliadores as $membro)
                        @php
                            $av = $membro->avaliacoes->firstWhere('barema_id', $barema->id);
                            $nf = $av?->nota_final;
                            if ($nf !== null) $notasFinais[] = $nf;
                        @endphp
                        <td class="num nota-cell">
                            {{ $nf !== null ? number_format($nf, 1, ',', '') : '—' }}
                        </td>
                    @endforeach
                    @if ($avaliadores->count() > 1)
                        <td class="num nota-cell">
                            {{ count($notasFinais) > 0 ? number_format(array_sum($notasFinais) / count($notasFinais), 1, ',', '') : '—' }}
                        </td>
                    @endif
                </tr>
            </tbody>
        </table>
    @endforeach

    {{-- Avaliadores --}}
    <h2>Composição da banca</h2>
    <table>
        <thead>
            <tr>
                <th>Avaliador</th>
                <th>Vínculo</th>
                @foreach ($baremas as $barema)
                    <th class="num">{{ str_contains($barema->tipo, 'confidencial') ? 'Conf.' : 'Avaliação' }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($avaliadores as $membro)
                @php
                    $dpv      = $membro->docenteProgramaVinculo;
                    $nome     = $dpv?->docenteProfile?->user?->name ?? '—';
                    $vinculo  = $dpv?->tipo_vinculo === 'interno' ? 'Interno' : 'Externo';
                @endphp
                <tr>
                    <td>{{ $nome }}</td>
                    <td>{{ $vinculo }}</td>
                    @foreach ($baremas as $barema)
                        @php
                            $av = $membro->avaliacoes->firstWhere('barema_id', $barema->id);
                            $st = $av?->status ?? 'pendente';
                            $st_label = match($st) { 'enviada' => 'Enviada', 'rascunho' => 'Rascunho', default => 'Pendente' };
                        @endphp
                        <td class="num">{{ $st_label }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Documento gerado pelo Sistema de Avaliação de Bancas — PPGMSB/UNEB</p>
        <p>{{ now()->format('d/m/Y \à\s H:i') }}</p>
    </div>

</div>
</body>
</html>
