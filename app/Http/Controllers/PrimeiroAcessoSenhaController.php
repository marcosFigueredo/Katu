<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PrimeiroAcessoSenhaController extends Controller
{
    public function edit()
    {
        return view('auth.primeiro-acesso-senha');
    }

    public function update(Request $request)
    {
        $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ], [
            'password.required' => 'Informe a nova senha.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
        ]);

        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->password),
            'primeiro_acesso' => false,
        ]);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Senha alterada com sucesso.');
    }
}