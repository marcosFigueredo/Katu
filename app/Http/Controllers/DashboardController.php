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
            'sec' => $this->painelSecretaria(),
            'docente' => redirect()->route('avaliacoes.index'),
            'discente' => view('dashboards.discente'),
            default => abort(403),
        };
    }

    private function painelSecretaria()
    {
        $programa = $this->programaDaSecretaria();

        $totalDiscentes = \App\Models\DiscenteProgramaVinculo::where('programa_id', $programa->id)->where('ativo', true)->count();
        $totalDocentes = \App\Models\DocenteProgramaVinculo::where('programa_id', $programa->id)->where('ativo', true)->count();
        $totalSecretaria = \App\Models\ProgramaUsuario::where('programa_id', $programa->id)->where('ativo', true)->count();

        return view('dashboards.secretaria', [
            'totalDiscentes' => $totalDiscentes,
            'totalDocentes'  => $totalDocentes,
            'totalUsuarios'  => $totalDiscentes + $totalDocentes + $totalSecretaria,
            'totalProgramas' => 1,
        ]);
    }
}
