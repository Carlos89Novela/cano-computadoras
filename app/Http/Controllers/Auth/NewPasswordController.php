<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Controlador para el Restablecimiento Final de Contraseña de Usuarios.
 *
 * Gestiona el paso definitivo de recuperación de credenciales cuando el usuario
 * accede mediante el token temporal enviado a su correo electrónico:
 * 1. Muestra el formulario para ingresar la nueva clave y su confirmación.
 * 2. Valida la vigencia y correspondencia del token de seguridad con el correo.
 * 3. Actualiza el hash de la contraseña, regenera el remember_token y dispara el evento `PasswordReset`.
 * 4. Redirige al inicio de sesión informando el resultado.
 */
class NewPasswordController extends Controller
{
    /**
     * Muestra la vista del formulario para definir una nueva contraseña.
     *
     * @param  Request  $request  Petición HTTP entrante con el token y correo en query parameters.
     * @return View Vista Blade con los campos de nueva contraseña.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Valida el token y procesa la actualización de la nueva contraseña.
     *
     * @param  Request  $request  Petición con token, email, password y password_confirmation.
     * @return RedirectResponse Redirección al login con mensaje de éxito o retorno con errores.
     *
     * @throws ValidationException Si los campos de entrada no cumplen con las reglas requeridas.
     */
    public function store(Request $request): RedirectResponse
    {
        // Valida la estructura y robustez requerida para contraseñas en la aplicación
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Ejecuta el restablecimiento a través del Password Broker de Laravel
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request): void {
                // Actualiza el hash Bcrypt/Argon2 y refresca el remember_token de sesión
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Emite el evento del sistema para notificaciones o invalidación de tokens API
                event(new PasswordReset($user));
            }
        );

        // Si la operación fue exitosa, redirige al login; si el token expiró o es inválido, regresa con error
        return $status == Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
    }
}
