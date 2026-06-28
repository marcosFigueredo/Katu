<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Resultado da Banca</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6; max-width: 600px; margin: 0 auto;">

    @php
        $trabalho  = $banca->trabalhoAcademico;
        $tipoLabel = $banca->tipo === 'qualificacao' ? 'Qualificação' : 'Defesa';
        $resultadoLabel = match($banca->resultado) {
            'aprovado'              => 'Aprovado',
            'aprovado_modificacoes' => 'Aprovado com modificações',
            'reprovado'             => 'Reprovado',
            default                 => '—',
        };
        $resultadoCor = match($banca->resultado) {
            'aprovado'              => '#16a34a',
            'aprovado_modificacoes' => '#ca8a04',
            'reprovado'             => '#dc2626',
            default                 => '#6b7280',
        };
    @endphp

    <div style="background:#00303A; padding:24px 32px; border-radius:8px 8px 0 0;">
        <h1 style="color:#fff; font-size:20px; margin:0;">
            PPGMSB — Resultado da Banca de {{ $tipoLabel }}
        </h1>
    </div>

    <div style="background:#f9fafb; padding:24px 32px; border-radius:0 0 8px 8px; border:1px solid #e5e7eb; border-top:none;">

        <p>Prezado(a) <strong>{{ $discente->name }}</strong>,</p>

        <p>
            Sua banca de <strong>{{ $tipoLabel }}</strong> foi concluída.
            Confira abaixo o resultado:
        </p>

        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:20px 24px; margin:20px 0;">
            <p style="margin:0 0 8px; color:#6b7280; font-size:14px;">Resultado</p>
            <p style="margin:0; font-size:24px; font-weight:bold; color:{{ $resultadoCor }};">
                {{ $resultadoLabel }}
            </p>

            @if ($banca->nota_media !== null)
                <hr style="border:none; border-top:1px solid #e5e7eb; margin:16px 0;">
                <p style="margin:0 0 4px; color:#6b7280; font-size:14px;">Nota média</p>
                <p style="margin:0; font-size:20px; font-weight:bold; color:#00303A;">
                    {{ number_format($banca->nota_media, 1, ',', '') }} / 10,0
                </p>
            @endif

            @if ($trabalho?->titulo)
                <hr style="border:none; border-top:1px solid #e5e7eb; margin:16px 0;">
                <p style="margin:0 0 4px; color:#6b7280; font-size:14px;">Trabalho</p>
                <p style="margin:0; font-size:15px;">{{ $trabalho->titulo }}</p>
            @endif

            @if ($banca->data_hora)
                <p style="margin:8px 0 0; color:#6b7280; font-size:14px;">
                    Data da banca: {{ $banca->data_hora->format('d/m/Y \à\s H:i') }}
                </p>
            @endif
        </div>

        @if ($banca->resultado === 'aprovado_modificacoes')
            <p style="color:#92400e; background:#fef3c7; border-radius:6px; padding:12px 16px; font-size:14px;">
                <strong>Atenção:</strong> Seu trabalho foi aprovado com modificações.
                Você deverá incorporar as sugestões da banca antes da entrega final.
                Entre em contato com seu orientador para mais informações.
            </p>
        @endif

        <hr style="border:none; border-top:1px solid #e5e7eb; margin:24px 0;">

        <p style="color:#6b7280; font-size:12px; margin:0;">
            PPGMSB — Programa de Pós-Graduação em Modelagem e Simulação de Biossistemas<br>
            Universidade do Estado da Bahia (UNEB)
        </p>
    </div>

</body>
</html>
