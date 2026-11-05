<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PropietarioMiddleware
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $usuario = $request->user();

        if (
            ! $usuario instanceof User
            || ! $usuario->hasRole('administrador')
            || ! $usuario->esPropietario()
        ) {
            abort(
                403,
                'Solo el propietario puede administrar roles y responsabilidades.'
            );
        }

        return $next($request);
    }
}
