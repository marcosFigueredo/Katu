<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Sistema para Avaliação de Bancas PPGMSB</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        html,
        body {
            height: 100%;
        }

        body {
            margin: 0;
            background-color: #00303A;
        }

        .login-page {
            height: 100vh;
            overflow: hidden;
            background-color: #00303A;
        }

        .login-left {
            height: 100vh;
            display: flex;
            align-items: center;
        }

        .login-card-wrapper {
            height: 100vh;
            display: flex;
            align-items: center;
        }

        .login-card {
            width: 100%;
        }

        @media (max-width: 991.98px) {
            body {
                background-color: #f4f6f8;
            }

            .login-page {
                min-height: 100vh;
                height: auto;
                overflow: auto;
                background-color: #f4f6f8;
                padding: 1rem 0;
            }

            .login-card-wrapper {
                min-height: 100vh;
                height: auto;
                align-items: center;
            }
        }
    </style>
</head>

<body>

    <main class="login-page">
        <div class="container h-100">
            <div class="row h-100 align-items-center">

                <div class="col-lg-7 d-none d-lg-block">
                    <div class="login-left text-white pe-xl-5">

                        <div>
                            <img src="{{ asset('images/logoBranco.png') }}"
                                alt="PPGMSB"
                                class="img-fluid mb-2"
                                style="max-width: 240px;">

                            <hr class="border-light opacity-50 mb-4">

                            <h1 class="display-6 fw-bold mb-4">
                                Sistema para Avaliação de Bancas
                            </h1>

                            <p class="lead mb-4">
                                Ambiente institucional destinado ao acompanhamento, registro e avaliação
                                de bancas de qualificação e defesa do Programa de Pós-Graduação em
                                Modelagem e Simulação de Biossistemas da UNEB.
                            </p>

                            <p class="mb-4 opacity-75">
                                O sistema permite organizar dados de discentes, docentes, orientações,
                                composição de bancas, avaliações, pareceres e informações estratégicas
                                para a gestão acadêmica e a autoavaliação do PPGMSB.
                            </p>

                            <a href="https://www.ppgmsb.uneb.br"
                                class="btn btn-outline-light btn-lg rounded-pill px-4">
                                Retornar ao site do PPGMSB
                            </a>
                        </div>

                    </div>
                </div>

                <div class="col-12 col-lg-5">
                    <div class="login-card-wrapper">
                        <div class="login-card">

                            <div class="card border-0 rounded-4 shadow-lg">
                                <div class="card-body p-4 p-md-5">

                                    <div class="mb-4 text-center text-lg-start">
                                        <h2 class="h3 fw-bold mb-2" style="color: #00303A;">
                                            Acesso ao Sistema
                                        </h2>

                                        <p class="text-muted mb-0">
                                            Entre com seu e-mail e senha cadastrados.
                                        </p>
                                    </div>

                                    @if (session('status'))
                                        <div class="alert alert-success">
                                            {{ session('status') }}
                                        </div>
                                    @endif

                                    @if ($errors->any())
                                        <div class="alert alert-danger">
                                            <div class="fw-semibold mb-1">
                                                Não foi possível realizar o login.
                                            </div>

                                            <ul class="mb-0 ps-3">
                                                @foreach ($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('login') }}">
                                        @csrf

                                        <div class="form-floating mb-3">
                                            <input type="email"
                                                class="form-control"
                                                name="email"
                                                id="email"
                                                value="{{ old('email') }}"
                                                placeholder="nome@exemplo.com"
                                                required
                                                autofocus
                                                autocomplete="username">

                                            <label for="email">
                                                E-mail
                                            </label>
                                        </div>

                                        <div class="form-floating mb-3">
                                            <input type="password"
                                                class="form-control"
                                                name="password"
                                                id="password"
                                                placeholder="Senha"
                                                required
                                                autocomplete="current-password">

                                            <label for="password">
                                                Senha
                                            </label>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mb-4">
                                            <div class="form-check">
                                                <input class="form-check-input"
                                                    type="checkbox"
                                                    name="remember"
                                                    id="remember_me">

                                                <label class="form-check-label text-secondary" for="remember_me">
                                                    Manter conectado
                                                </label>
                                            </div>

                                            @if (Route::has('password.request'))
                                                <a href="{{ route('password.request') }}"
                                                    class="text-decoration-none"
                                                    style="color: #00303A;">
                                                    Esqueci minha senha
                                                </a>
                                            @endif
                                        </div>

                                        <div class="d-grid">
                                            <button type="submit"
                                                class="btn btn-lg text-white fw-semibold rounded-pill"
                                                style="background-color: #00303A;">
                                                Entrar
                                            </button>
                                        </div>
                                    </form>

                                    <div class="border-top mt-4 pt-4">
                                        <p class="small text-muted mb-0">
                                            Acesso restrito a usuários cadastrados pela secretaria do programa.
                                            No primeiro acesso, será solicitada a alteração da senha temporária.
                                        </p>
                                    </div>

                                </div>
                            </div>

                            <div class="text-center mt-3 d-lg-none">
                                <a href="https://www.ppgmsb.uneb.br"
                                    class="small text-decoration-none"
                                    style="color: #00303A;">
                                    Retornar ao site do PPGMSB
                                </a>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

</body>
</html>