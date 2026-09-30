<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador de Aviso y Recordatorio de Verificación de Correo.
 *
 * Intercepta al usuario cuando intenta ingresar a rutas protegidas por el
 * middleware 'verified' sin haber completado la verificación de su correo.
 * Si ya está verificado, lo redirige al panel; de lo contrario, muestra
 * la pantalla con instrucciones y el botón para solicitar nuevo enlace.
 */
class EmailVerificationPromptController extends Controller
{
    /**
     * Muestra la pantalla de recordatorio de verificación o redirige al panel si ya está verificado.
     *
     * @param  Request  $request  Petición HTTP con el usuario autenticado.
     * @return RedirectResponse|View Redirección o vista Blade con el aviso de verificación pendiente.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        return $request->user()?->hasVerifiedEmail()
            ? redirect()->intended(route('dashboard', absolute: false))
            : view('auth.verify-email');
    }
}
