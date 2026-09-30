<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador para el Reenvío de Notificaciones de Verificación de Correo.
 *
 * Permite que los usuarios cuyo correo electrónico aún no ha sido confirmado
 * soliciten el reenvío de un nuevo enlace firmado temporal con instrucciones
 * para validar la propiedad de su cuenta.
 */
class EmailVerificationNotificationController extends Controller
{
    /**
     * Envía una nueva notificación por correo con el enlace de verificación firmado.
     *
     * @param  Request  $request  Petición HTTP entrante con el usuario autenticado.
     * @return RedirectResponse Redirección a la vista previa con estado flash de confirmación de envío.
     */
    public function store(Request $request): RedirectResponse
    {
        // Si la cuenta ya tiene el correo verificado, se omite el envío y se envía al panel
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        // Dispara la notificación interna de verificación de correo
        $request->user()?->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}

