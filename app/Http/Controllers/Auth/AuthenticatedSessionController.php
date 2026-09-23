<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auditoria\RegistrarAcceso;
use App\Services\RedireccionPorRol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(
        LoginRequest $request,
        RedireccionPorRol $redireccionPorRol,
        RegistrarAcceso $registrarAcceso
    ): RedirectResponse {
        $request->authenticate();

        $request->session()->regenerate();

        $usuario = $request->user();

        abort_unless(
            $usuario instanceof User,
            403
        );

        $registrarAcceso->registrar(
            evento: 'inicio_exitoso',
            resultado: 'exitoso',
            request: $request,
            usuario: $usuario
        );

        return redirect()->intended(
            $redireccionPorRol->ruta($usuario)
        );
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(
        Request $request,
        RegistrarAcceso $registrarAcceso
    ): RedirectResponse {
        $usuario = $request->user();

        if ($usuario instanceof User) {
            $registrarAcceso->registrar(
                evento: 'cierre_sesion',
                resultado: 'exitoso',
                request: $request,
                usuario: $usuario
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
