<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Auditoria\RegistrarAcceso;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Solicitud de Validación y Seguridad para el Inicio de Sesión.
 *
 * Encapsula la autenticación robusta y la defensa contra ataques de fuerza bruta:
 * - Soporte de autenticación dual: Correo Electrónico Institucional o Número de Empleado.
 * - Validación sintáctica de identificador y contraseña.
 * - Limitación de tasa (Rate Limiting) por identificador normalizado e IP (máximo 5 intentos).
 * - Telemetría de seguridad automática mediante `RegistrarAcceso`:
 *   - Registra eventos de 'inicio_fallido' ante credenciales incorrectas.
 *   - Registra eventos de 'bloqueo_temporal' cuando se activa el límite de intentos.
 * - Despacho del evento `Lockout` para alertas del sistema.
 */
class LoginRequest extends FormRequest
{
    /**
     * Determina si el cliente tiene autorización para realizar la petición.
     *
     * @return bool Siempre verdadero dado que es un endpoint público de autenticación.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para el inicio de sesión.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Intenta autenticar las credenciales proporcionadas.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        // Verifica si la IP y el identificador están temporalmente bloqueados por exceso de intentos
        $this->ensureIsNotRateLimited();

        $login = trim((string) $this->input('login'));
        $esCorreo = (bool) filter_var($login, FILTER_VALIDATE_EMAIL);
        $campoIdentificador = $esCorreo ? 'email' : 'numero_empleado';

        $credenciales = [
            $campoIdentificador => $login,
            'password' => (string) $this->input('password'),
        ];

        // Intenta autenticar con el guard web de Laravel
        if (! Auth::attempt($credenciales, $this->boolean('remember'))) {
            // Incrementa el contador de intentos fallidos en la clave de limitación
            RateLimiter::hit($this->throttleKey());

            // Intenta localizar al usuario para enriquecer la telemetría de auditoría
            /** @var User|null $usuario */
            $usuario = User::query()
                ->where('email', $login)
                ->orWhere('numero_empleado', $login)
                ->first();

            $correoAuditoria = $usuario instanceof User ? $usuario->email : $login;

            // Registra el fallo de autenticación en la bitácora de seguridad
            app(RegistrarAcceso::class)->registrar(
                evento: 'inicio_fallido',
                resultado: 'fallido',
                request: $this,
                usuario: $usuario,
                correoIntentado: $correoAuditoria,
                motivoFallo: 'Credenciales inválidas.'
            );

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        // Si la autenticación fue exitosa, limpia los contadores de fallos acumulados
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Garantiza que la petición no haya superado el umbral máximo de intentos permitidos.
     *
     * @throws ValidationException Si el limitador detecta más de 5 intentos fallidos consecutivos.
     */
    public function ensureIsNotRateLimited(): void
    {
        // Límite fijado en 5 intentos fallidos antes del bloqueo temporal
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        // Dispara el evento del framework para observadores de seguridad
        event(new Lockout($this));

        // Calcula los segundos restantes antes de que expire la penalización
        $seconds = RateLimiter::availableIn($this->throttleKey());

        $login = trim((string) $this->input('login'));

        // Registra el incidente de bloqueo temporal por fuerza bruta
        app(RegistrarAcceso::class)->registrar(
            evento: 'bloqueo_temporal',
            resultado: 'bloqueado',
            request: $this,
            correoIntentado: $login,
            motivoFallo: 'Demasiados intentos de inicio de sesión.',
            metadatos: [
                'segundos_restantes' => $seconds,
            ]
        );

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Construye la clave única de limitación de tasa basada en el identificador normalizado y la IP.
     *
     * @return string Clave de limitación de tasa.
     */
    public function throttleKey(): string
    {
        $login = trim((string) $this->input('login'));

        return Str::transliterate(Str::lower($login).'|'.$this->ip());
    }
}
