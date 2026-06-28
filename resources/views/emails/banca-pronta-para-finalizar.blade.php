<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Todas as avaliações recebidas — PPGMSB</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 15px; color: #222; background: #f5f5f5; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #00303A; color: #fff; padding: 28px 32px 20px; }
        .header h1 { margin: 0 0 4px; font-size: 20px; }
        .header p  { margin: 0; font-size: 13px; opacity: .8; }
        .body { padding: 28px 32px; }
        .info-box { background: #f0f9ff; border-left: 4px solid #00303A; padding: 14px 18px; border-radius: 4px; margin: 18px 0; }
        .info-box p { margin: 4px 0; font-size: 14px; }
        .btn { display: inline-block; background: #00303A; color: #fff; padding: 12px 28px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 15px; margin-top: 20px; }
        .footer { padding: 18px 32px; font-size: 12px; color: #888; border-top: 1px solid #eee; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Todas as avaliações foram recebidas</h1>
        <p>Programa de Pós-Graduação em Modelagem em Saúde da Bahia — PPGMSB / UNEB</p>
    </div>
    <div class="body">
        <p>Prezado(a) <strong>{{ $orientador->name }}</strong>,</p>

        <p>
            Todas as avaliações da banca de
            <strong>{{ str_starts_with($banca->tipo, 'defesa') ? 'Defesa' : 'Qualificação' }}</strong>
            foram enviadas pelos avaliadores. A banca já pode ser finalizada.
        </p>

        @php
            $discente = $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user;
            $titulo   = $banca->trabalhoAcademico?->titulo;
        @endphp

        <div class="info-box">
            @if ($discente)
                <p><strong>Discente:</strong> {{ $discente->name }}</p>
            @endif
            @if ($titulo)
                <p><strong>Trabalho:</strong> {{ $titulo }}</p>
            @endif
            @if ($banca->data_hora)
                <p><strong>Data da banca:</strong> {{ $banca->data_hora->format('d/m/Y \à\s H:i') }}</p>
            @endif
        </div>

        <p>
            Acesse o sistema para registrar o resultado da banca e notificar o discente.
        </p>

        <a href="{{ route('avaliacoes.banca', $banca) }}" class="btn">
            Acessar a banca
        </a>
    </div>
    <div class="footer">
        Esta mensagem foi gerada automaticamente pelo sistema de defesas do PPGMSB/UNEB.
        Não responda a este e-mail.
    </div>
</div>
</body>
</html>
