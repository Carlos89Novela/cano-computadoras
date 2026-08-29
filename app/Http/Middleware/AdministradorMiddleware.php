<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdministradorMiddleware
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        if (
            !$request->user() ||
            !$request->user()->hasRole('administrador')
        ) {
            abort(
                403,
                'No tienes permiso para acceder al panel administrativo.'
            );
        }

        return $next($request);
    }
}