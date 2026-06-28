<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\RecuperacaoSenhaTemporariaMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Generate a temporary password and send it to the registered e-mail.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $usuario = User::where('email', $request->email)->first();

        if (! $usuario) {
            return back()->with('status', 'Se o e-mail estiver cadastrado, uma nova senha temporária será enviada.');
        }

        if (! $usuario->ativo) {
            return back()->with('status', 'Este usuário está inativo. Entre em contato com a secretaria do programa.');
        }

        $senhaTemporaria = 'PPG-' . Str::upper(Str::random(4)) . '-' . random_int(1000, 9999);

        $usuario->update([
            'password' => Hash::make($senhaTemporaria),
            'primeiro_acesso' => true,
        ]);

        Mail::to($usuario->email)->send(
            new RecuperacaoSenhaTemporariaMail(
                $usuario,
                $senhaTemporaria,
                route('login')
            )
        );

        return back()->with('status', 'Se o e-mail estiver cadastrado, uma nova senha temporária será enviada.');
    }
}