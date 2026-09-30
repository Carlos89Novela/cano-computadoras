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

/**
 * Controlador para la Gestión de Sesiones de Autenticación de Usuarios.
 *
 * Administra el ciclo de vida de la sesión web:
 * 1. Presentación del formulario de inicio de sesión.
 * 2. Autenticación con limitación de tasa (rate limiting) y prevención de fijación de sesión.
 * 3. Telemetría de auditoría de accesos exitosos en bitácora de seguridad.
 * 4. Enrutamiento inteligente posterior al inicio de sesión según el rol asignado (Admin, Supervisor, Empleado, Cliente).
 * 5. Cierre de sesión seguro con invalidación de sesión, regeneración de token CSRF y registro de salida.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Muestra la vista del formulario de inicio de sesión.
     *
     * @return View Vista Blade con el formulario de credenciales.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Procesa la solicitud entrante de autenticación de credenciales.
     *
     * @param  LoginRequest  $request  Petición que encapsula la validación y el control de intentos fallidos.
     * @param  RedireccionPorRol  $redireccionPorRol  Servicio que determina la URL destino según el rol del usuario.
     * @param  RegistrarAcceso  $registrarAcceso  Servicio de telemetría de accesos y seguridad.
     * @return RedirectResponse Redirección a la ruta prevista o al panel correspondiente por rol.
     */
    public function store(
        LoginRequest $request,
        RedireccionPorRol $redireccionPorRol,
        RegistrarAcceso $registrarAcceso
    ): RedirectResponse {
        // Valida credenciales contra la base de datos y gestiona el limitador de tasa de intentos fallidos
        $request->authenticate();

        // Regenera el ID de sesión para prevenir ataques de fijación de sesión (Session Fixation)
        $request->session()->regenerate();

        $usuario = $request->user();

        // Salvaguarda de tipado estricto
        abort_unless(
            $usuario instanceof User,
            403
        );

        // Registra el evento de inicio de sesión exitoso en la bitácora de auditoría y accesos
        $registrarAcceso->registrar(
            evento: 'inicio_exitoso',
            resultado: 'exitoso',
            request: $request,
            usuario: $usuario
        );

        // Redirecciona a la URL previa prevista (intended) o a la ruta base de su rol operativo
        return redirect()->intended(
            $redireccionPorRol->ruta($usuario)
        );
    }

    /**
     * Destruye la sesión autenticada actual y desvincula al usuario.
     *
     * @param  Request  $request  Petición HTTP entrante.
     * @param  RegistrarAcceso  $registrarAcceso  Servicio de telemetría para registrar el cierre de sesión.
     * @return RedirectResponse Redirección a la página de bienvenida principal.
     */
    public function destroy(
        Request $request,
        RegistrarAcceso $registrarAcceso
    ): RedirectResponse {
        $usuario = $request->user();

        // Registra la salida voluntaria del usuario antes de destruir la sesión
        if ($usuario instanceof User) {
            $registrarAcceso->registrar(
                evento: 'cierre_sesion',
                resultado: 'exitoso',
                request: $request,
                usuario: $usuario
            );
        }

        // Cierra la sesión en el guard web de Laravel
        Auth::guard('web')->logout();

        // Invalida la sesión actual y limpia todos los datos almacenados
        $request->session()->invalidate();

        // Regenera el token de protección CSRF para peticiones subsiguientes
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
