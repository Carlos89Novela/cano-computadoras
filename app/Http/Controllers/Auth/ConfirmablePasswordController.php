<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Controlador para la Confirmación de Contraseña en Áreas Seguras.
 *
 * Exige al usuario autenticado revalidar su contraseña actual antes de acceder
 * a secciones o realizar operaciones de alto impacto (cambio de correo, eliminación de cuenta, etc.).
 * Al tener éxito, almacena una marca temporal en sesión (`auth.password_confirmed_at`)
 * que autoriza el acceso durante una ventana de tiempo preconfigurada.
 */
class ConfirmablePasswordController extends Controller
{
    /**
     * Muestra la vista para solicitar la confirmación de la contraseña del usuario.
     *
     * @return View Vista Blade con el formulario de confirmación.
     */
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    /**
     * Valida la contraseña ingresada y autoriza la sesión si coincide con las credenciales del usuario.
     *
     * @param  Request  $request  Petición HTTP entrante con el campo 'password'.
     * @return RedirectResponse Redirección a la ruta prevista originalmente antes del desafío de seguridad.
     *
     * @throws ValidationException Si la contraseña no coincide con la registrada para el usuario activo.
     */
    public function store(Request $request): RedirectResponse
    {
        // Verifica la contraseña contra el guard web sin iniciar una nueva sesión
        if (! Auth::guard('web')->validate([
            'email' => $request->user()?->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        // Guarda la marca de tiempo para satisfacer el middleware 'password.confirm'
        $request->session()->put('auth.password_confirmed_at', time());

        // Redirecciona al recurso previsto o a la ruta base de navegación
        return redirect()->intended(route('dashboard', absolute: false));
    }
}

