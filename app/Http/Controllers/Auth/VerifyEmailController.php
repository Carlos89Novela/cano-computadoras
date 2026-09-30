<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Controlador para la Confirmación de Correo Electrónico.
 *
 * Procesa la pulsación del enlace firmado temporal recibido por el usuario:
 * 1. Verifica la firma criptográfica y la vigencia del enlace (`EmailVerificationRequest`).
 * 2. Comprueba si el correo ya había sido validado previamente.
 * 3. En caso de ser la primera validación, marca la cuenta como verificada (`email_verified_at`)
 *    y emite el evento `Verified`.
 * 4. Redirige al panel principal con la bandera de confirmación `?verified=1`.
 */
class VerifyEmailController extends Controller
{
    /**
     * Marca la dirección de correo electrónico del usuario autenticado como verificada.
     *
     * @param  EmailVerificationRequest  $request  Petición que valida automáticamente la firma del enlace.
     * @return RedirectResponse Redirección al panel con confirmación visual de verificación.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Salvaguarda: garantiza que el modelo User implemente la interfaz MustVerifyEmail
        abort_unless($user instanceof MustVerifyEmail, 403);

        // Si ya estaba verificado con anterioridad, redirige sin disparar eventos duplicados
        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        // Marca el campo email_verified_at y emite el evento Verified
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}

