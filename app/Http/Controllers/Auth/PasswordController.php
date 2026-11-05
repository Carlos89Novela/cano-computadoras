<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Controlador para la Actualización de Contraseña desde el Perfil de Usuario.
 *
 * Permite a cualquier usuario autenticado cambiar su clave de acceso desde la
 * sección de configuración de cuenta, exigiendo verificar primero su contraseña
 * actual y cumplir los estándares de seguridad definidos para la nueva contraseña.
 */
class PasswordController extends Controller
{
    /**
     * Valida y actualiza la contraseña del usuario actualmente autenticado.
     *
     * @param  Request  $request  Petición HTTP con current_password, password y password_confirmation.
     * @return RedirectResponse Retorno a la pantalla previa con flash de estado de éxito.
     */
    public function update(Request $request): RedirectResponse
    {
        // Valida asociando los posibles mensajes de error al bag 'updatePassword' para la vista Blade
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        // Aplica el hash y persiste la nueva contraseña del usuario
        $request->user()?->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}
