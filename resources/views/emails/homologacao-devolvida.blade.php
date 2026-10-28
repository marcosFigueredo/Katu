<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Pedido de homologação devolvido</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6; max-width: 640px; margin: 0 auto;">

    @php
        $trabalho = $banca->trabalhoAcademico;
        $discente = $trabalho?->discenteVinculo?->discenteProfile?->user;
        $etapa = $banca->tipo === 'defesa' ? 'Defesa' : 'Qualificação';
    @endphp

    <div style="background:#b45309; padding:24px 32px; border-radius:8px 8px 0 0;">
        <h1 style="color:#fff; font-size:20px; margin:0;">Pedido de Homologação Devolvido</h1>
    </div>

    <div style="background:#f9fafb; padding:24px 32px; border-radius:0 0 8px 8px; border:1px solid #e5e7eb; border-top:none;">

        <p>Prezado(a) <strong>{{ $discente?->name ?? 'Discente' }}</strong>,</p>

        <p>
            Seu pedido de homologação da banca de <strong>{{ strtolower($etapa) }}</strong> foi devolvido pela Secretaria para ajustes.
        </p>

        <div style="background:#fffbeb; border:1px solid #fcd34d; border-radius:6px; padding:16px; margin:16px 0; white-space:pre-line;">{{ $naoConformidade }}</div>

        <p>
            Acesse o sistema em <strong>Meu Trabalho</strong> para revisar a banca e reenviar o pedido à homologação.
        </p>

        <p style="margin-top:20px;">
            <a href="{{ route('meu-trabalho.index') }}"
               style="background:#00303A; color:#fff; text-decoration:none; padding:10px 20px; border-radius:6px; display:inline-block; font-weight:bold;">
                Acessar Meu Trabalho
            </a>
        </p>
    </div>

</body>
</html>

