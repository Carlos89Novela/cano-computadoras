<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RedireccionPorRol;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Controlador de Registro Público de Nuevos Clientes.
 *
 * Permite a los clientes crear de forma autónoma una cuenta en el sistema:
 * 1. Muestra la pantalla de captura de datos de nuevo usuario.
 * 2. Valida la unicidad del correo electrónico y la fortaleza de la contraseña.
 * 3. Persiste el nuevo registro de usuario y le asigna el rol 'cliente' por defecto.
 * 4. Emite el evento `Registered` para disparar el correo de verificación.
 * 5. Inicia la sesión del usuario de forma inmediata y lo direcciona a su panel.
 */
class RegisteredUserController extends Controller
{
    /**
     * Muestra la vista del formulario de registro de clientes.
     *
     * @return View Vista Blade con los campos de alta de usuario.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Valida y almacena un nuevo usuario cliente en el sistema.
     *
     * @param  Request  $request  Petición HTTP entrante con name, email y password.
     * @param  RedireccionPorRol  $redireccionPorRol  Servicio que determina la URL del panel según el rol asignado.
     * @return RedirectResponse Redirección al panel de cliente.
     *
     * @throws ValidationException Si la validación de campos falla.
     */
    public function store(
        Request $request,
        RedireccionPorRol $redireccionPorRol
    ): RedirectResponse {
        // Validación estricta de datos de registro
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:'.User::class,
            ],
            'password' => [
                'required',
                'confirmed',
                Rules\Password::defaults(),
            ],
        ]);

        // Crea la cuenta de usuario con contraseña protegida mediante hash
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => Hash::make(
                $request->string('password')->toString()
            ),
        ]);

        // Asigna automáticamente el rol de cliente al nuevo usuario registrado
        $user->assignRole('cliente');

        // Dispara el evento del framework para el envío del correo de verificación
        event(new Registered($user));

        // Inicia la sesión del nuevo usuario en el sistema
        Auth::login($user);

        // Redirige al panel correspondiente al nuevo cliente registrado
        return redirect(
            $redireccionPorRol->ruta($user)
        );
    }
}

