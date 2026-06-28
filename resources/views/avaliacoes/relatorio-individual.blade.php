<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Avaliação — {{ $barema->nome }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #1f2937; background: #fff; }

        .page { max-width: 800px; margin: 0 auto; padding: 32px 40px; }

        .header { border-bottom: 3px solid #00303A; padding-bottom: 16px; margin-bottom: 24px; }
        .header h1 { font-size: 18px; color: #00303A; margin-bottom: 4px; }
        .header p  { font-size: 12px; color: #6b7280; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; }
        .info-item label { display: block; font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; }
        .info-item p { font-size: 13px; font-weight: 600; margin-top: 2px; }

        .secao { margin-bottom: 20px; }
        .secao-header {
            background: #00303A; color: #fff;
            padding: 8px 12px; font-weight: 600; font-size: 13px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .item { border: 1px solid #e5e7eb; border-top: none; padding: 10px 12px; }
        .item:last-child { border-bottom: 1px solid #e5e7eb; }
        .item-nome { font-weight: 600; font-size: 13px; }
        .item-desc { font-size: 11px; color: #6b7280; margin-top: 2px; }
        .item-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
        .nota-box { text-align: right; min-width: 100px; flex-shrink: 0; }
        .nota-valor { font-size: 20px; font-weight: 700; color: #00303A; }
        .nota-max { font-size: 11px; color: #6b7280; }
        .obs-text { font-size: 11px; color: #374151; margin-top: 6px; font-style: italic; }

        .total-box {
            border: 2px solid #00303A; border-radius: 6px;
            padding: 16px 20px; margin: 24px 0;
            display: flex; justify-content: space-between; align-items: center;
        }
        .total-label { font-size: 14px; font-weight: 600; color: #00303A; }
        .total-valor { font-size: 28px; font-weight: 700; color: #00303A; }

        .obs-gerais { border: 1px solid #e5e7eb; border-radius: 4px; padding: 12px; margin-bottom: 24px; }
        .obs-gerais-label { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }

        .assinatura { margin-top: 48px; border-top: 1px solid #9ca3af; padding-top: 8px; text-align: center; }
        .assinatura p { font-size: 12px; color: #6b7280; margin-top: 4px; }

        .confidencial-badge {
            background: #1e1b4b; color: #fff; font-size: 11px; padding: 3px 10px;
            border-radius: 4px; display: inline-block; margin-left: 8px;
        }

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
    $discente     = $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user;
    $trabalho     = $banca->trabalhoAcademico;
    $avaliador    = $avaliacao->membro?->docenteProgramaVinculo?->docenteProfile?->user;
    $tipoLabel    = $banca->tipo === 'qualificacao' ? 'Qualificação' : 'Defesa';
    $confidencial = str_contains($barema->tipo, 'confidencial');
    $resultadoLabel = match($banca->resultado ?? '') {
        'aprovado'              => 'Aprovado',
        'aprovado_modificacoes' => 'Aprovado com modificações',
        'reprovado'             => 'Reprovado',
        default                 => null,
    };
@endphp

<div class="page">

    {{-- Botão imprimir (some na impressão) --}}
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
        <h1>
            PPGMSB — Avaliação de Banca de {{ $tipoLabel }}
            @if ($confidencial)
                <span class="confidencial-badge">CONFIDENCIAL</span>
            @endif
        </h1>
        <p>{{ $trabalho?->programa?->nome ?? 'PPGMSB' }} — Universidade do Estado da Bahia (UNEB)</p>
    </div>

    {{-- Informações gerais --}}
    <div class="info-grid">
        <div class="info-item">
            <label>Discente</label>
            <p>{{ $discente?->name ?? '—' }}</p>
        </div>
        <div class="info-item">
            <label>Avaliador</label>
            <p>{{ $avaliador?->name ?? '—' }}</p>
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
        <div class="info-item">
            <label>Barema</label>
            <p>{{ $barema->nome }} (v{{ $barema->versao }})</p>
        </div>
        @if ($avaliacao->enviada_em)
            <div class="info-item">
                <label>Avaliação enviada em</label>
                <p>{{ $avaliacao->enviada_em->format('d/m/Y \à\s H:i') }}</p>
            </div>
        @endif
        @if ($resultadoLabel)
            <div class="info-item">
                <label>Resultado da banca</label>
                <p>{{ $resultadoLabel }}</p>
            </div>
        @endif
    </div>

    {{-- Seções e itens --}}
    @foreach ($barema->secoes as $secao)
        <div class="secao">
            <div class="secao-header">
                <span>{{ $secao->nome }}</span>
                @if ($secao->peso != 1)
                    <span style="font-size:11px; opacity:.8;">Peso {{ number_format($secao->peso, 1) }}</span>
                @endif
            </div>

            @foreach ($secao->itens as $item)
                @php
                    $nota = $notasIndexadas[$item->id] ?? null;
                @endphp
                <div class="item">
                    <div class="item-row">
                        <div style="flex:1;">
                            <div class="item-nome">
                                {{ $item->nome }}
                                @if ($item->obrigatorio) <span style="color:#dc2626;">*</span> @endif
                            </div>
                            @if ($item->descricao)
                                <div class="item-desc">{{ $item->descricao }}</div>
                            @endif
                            @if ($nota?->observacao)
                                <div class="obs-text">Obs: {{ $nota->observacao }}</div>
                            @endif
                        </div>
                        <div class="nota-box">
                            <div class="nota-valor">
                                {{ $nota?->nota !== null ? number_format($nota->nota, 1, ',', '') : '—' }}
                            </div>
                            <div class="nota-max">de {{ number_format($item->nota_maxima, 1, ',', '') }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    {{-- Observações gerais --}}
    @if ($avaliacao->observacoes_gerais)
        <div class="obs-gerais">
            <div class="obs-gerais-label">Observações gerais do avaliador</div>
            <p style="line-height:1.6;">{{ $avaliacao->observacoes_gerais }}</p>
        </div>
    @endif

    {{-- Nota final --}}
    <div class="total-box">
        <div class="total-label">Nota final calculada</div>
        <div>
            <span class="total-valor">
                {{ $avaliacao->nota_final !== null ? number_format($avaliacao->nota_final, 1, ',', '') : '—' }}
            </span>
            <span style="font-size:14px; color:#6b7280;"> / 10,0</span>
        </div>
    </div>

    {{-- Assinatura --}}
    <div class="assinatura" style="max-width:300px; margin:48px auto 0;">
        <p>{{ $avaliador?->name ?? '________________________________________' }}</p>
        <p>Avaliador(a)</p>
    </div>

    <div class="footer">
        <p>Documento gerado pelo Sistema de Avaliação de Bancas — PPGMSB/UNEB</p>
        <p>{{ now()->format('d/m/Y \à\s H:i') }}</p>
    </div>

</div>
</body>
</html>
