<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Acesso ao Sistema de Avaliação de Bancas</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">

    <h2 style="color: #00303A;">
        Sistema de Avaliação de Bancas - PPGMSB
    </h2>

    <p>
        Prezado(a) {{ $usuario->name }},
    </p>

    <p>
        Seu acesso ao Sistema de Avaliação de Bancas do PPGMSB foi criado.
    </p>

    <p>
        Utilize os dados abaixo para realizar o primeiro acesso:
    </p>

    <p>
        <strong>Login:</strong> {{ $usuario->email }}<br>
        <strong>Senha temporária:</strong> {{ $senhaTemporaria }}
    </p>

    <p>
        Link de acesso:
        <br>
        <a href="{{ $urlLogin }}">
            {{ $urlLogin }}
        </a>
    </p>

    <p>
        No primeiro acesso, o sistema solicitará obrigatoriamente a alteração da senha temporária.
    </p>

    <p>
        Caso você não reconheça este cadastro, entre em contato com a secretaria do PPGMSB.
    </p>

    <hr>

    <p style="font-size: 13px; color: #666;">
        PPGMSB - Programa de Pós-Graduação em Modelagem e Simulação de Biossistemas<br>
        Universidade do Estado da Bahia - UNEB
    </p>

</body>
</html>