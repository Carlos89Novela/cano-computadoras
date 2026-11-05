<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

/**
 * Controlador de Perfil y Cuenta de Usuario.
 *
 * Administra las operaciones sobre la propia cuenta del usuario autenticado:
 * - Edición de datos generales (nombre, correo electrónico).
 * - Detección de cambio de correo: invalida la fecha de verificación (`email_verified_at = null`) para exigir re-verificación.
 * - Eliminación de cuenta: exige re-ingreso de la contraseña actual e impide la baja si el usuario posee órdenes de servicio activas o históricas.
 */
class ProfileController extends Controller
{
    /**
     * Muestra la interfaz para editar el perfil del usuario autenticado.
     *
     * @param  Request  $request  Petición HTTP entrante.
     * @return View Vista 'profile.edit' con la entidad del usuario.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Actualiza la información personal del usuario autenticado.
     *
     * @param  ProfileUpdateRequest  $request  Petición validada con nombre y correo electrónico.
     * @return RedirectResponse Redirección a la vista de perfil con estatus de confirmación.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        // Si el usuario alteró su correo electrónico, reinicia la marca de verificación
        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Da de baja definitivamente la cuenta del usuario.
     *
     * @param  Request  $request  Petición HTTP entrante con la contraseña de confirmación.
     * @return RedirectResponse Redirección al inicio público tras cerrar y destruir la sesión.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => [
                'required',
                'current_password',
            ],
        ]);

        $user = $request->user();

        // Integridad referencial: Bloquea la baja si el usuario tiene reparaciones en sistema
        if ($user->ordenesServicio()->exists()) {
            return back()->withErrors([
                'password' => 'No puedes eliminar tu cuenta porque tienes órdenes de reparación registradas.',
            ], 'userDeletion');
        }

        // Cierre de sesión y eliminación física del registro
        Auth::logout();

        $user->delete();

        // Invalidación de sesión y regeneración del token CSRF para prevenir ataques de fijación
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
