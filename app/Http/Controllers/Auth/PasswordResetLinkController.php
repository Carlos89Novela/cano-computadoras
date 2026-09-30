<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Controlador para la Solicitud de Enlaces de Restablecimiento de Contraseña.
 *
 * Expone la interfaz y la lógica de envío de correos electrónicos para
 * recuperación de cuenta:
 * 1. Muestra la pantalla "Olvidé mi contraseña".
 * 2. Valida la existencia y formato del correo provisto.
 * 3. Delega en el Password Broker la generación de un token criptográfico seguro
 *    y el envío del correo electrónico con el enlace de un solo uso.
 */
class PasswordResetLinkController extends Controller
{
    /**
     * Muestra la vista del formulario para solicitar el enlace de recuperación.
     *
     * @return View Vista Blade de recuperación de contraseña.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Procesa la solicitud y despacha el correo con el enlace de restablecimiento.
     *
     * @param  Request  $request  Petición HTTP entrante con el campo 'email'.
     * @return RedirectResponse Retorno a la vista previa con el estado o errores resultantes.
     *
     * @throws ValidationException Si el correo no cuenta con el formato requerido.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Genera el token y despacha la notificación por correo al destinatario
        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Retorna con confirmación o mensaje de error según la respuesta del broker
        return $status == Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
    }
}

