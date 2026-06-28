<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GarantirSenhaAlterada
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user()?->primeiro_acesso
            && ! $request->routeIs('primeiro-acesso-senha.edit')
            && ! $request->routeIs('primeiro-acesso-senha.update')
            && ! $request->routeIs('logout')
        ) {
            return redirect()->route('primeiro-acesso-senha.edit');
        }

        return $next($request);
    }
}