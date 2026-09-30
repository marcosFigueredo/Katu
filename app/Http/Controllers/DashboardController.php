<?php

namespace App\Http\Controllers;

use App\Models\Programa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user->ativo) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Usuário inativo. Procure a secretaria do programa.',
                ]);
        }
        if ($user->primeiro_acesso) {
            return redirect()->route('primeiro-acesso-senha.edit');
        }

        if ($user->categoria === 'sec' && ! app(\App\Services\ContextoSecretaria::class)->programa($user)) {
            return redirect()->route('programas.create');
        }

        return match ($user->categoria) {
            'sec' => view('dashboards.secretaria', [
                'totalDiscentes' => User::where('categoria', 'discente')->where('ativo', true)->count(),
                'totalDocentes'  => User::where('categoria', 'docente')->where('ativo', true)->count(),
                'totalUsuarios'  => User::where('ativo', true)->count(),
                'totalProgramas' => Programa::count(),
            ]),
            'docente' => redirect()->route('avaliacoes.index'),
            'discente' => view('dashboards.discente'),
            default => abort(403),
        };
    }
}
