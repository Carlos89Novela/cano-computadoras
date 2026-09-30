<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Auditoria\RegistrarAcceso;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Solicitud de Validación y Seguridad para el Inicio de Sesión.
 *
 * Encapsula la autenticación robusta y la defensa contra ataques de fuerza bruta:
 * - Validación sintáctica de correo y contraseña.
 * - Limitación de tasa (Rate Limiting) por combinación de correo normalizado e IP (máximo 5 intentos).
 * - Telemetría de seguridad automática mediante `RegistrarAcceso`:
 *   - Registra eventos de 'inicio_fallido' ante contraseñas incorrectas.
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
     * Reglas de validación aplicables a las credenciales provistas.
     *
     * @return array<string, ValidationRule|array<mixed>|string> Reglas de validación.
     */
    public function rules(): array
    {
        return [
            // Correo del usuario registrado
            'email' => ['required', 'string', 'email'],
            // Contraseña en texto plano para validación contra el hash
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Intenta autenticar las credenciales del usuario controlando el limitador de tasa.
     *
     * @throws ValidationException Si el límite de intentos fue excedido o las credenciales son inválidas.
     */
    public function authenticate(): void
    {
        // Verifica si la IP y el correo están temporalmente bloqueados por exceso de intentos
        $this->ensureIsNotRateLimited();

        // Intenta autenticar con el guard web de Laravel
        if (
            ! Auth::attempt(
                $this->only('email', 'password'),
                $this->boolean('remember')
            )
        ) {
            // Incrementa el contador de intentos fallidos en la clave de limitación
            RateLimiter::hit($this->throttleKey());

            $correo = (string) $this->input('email');

            // Intenta localizar si el usuario existe para enriquecer el registro de auditoría
            $usuario = User::query()
                ->where('email', $correo)
                ->first();

            // Registra el fallo de autenticación en la bitácora de seguridad
            app(RegistrarAcceso::class)->registrar(
                evento: 'inicio_fallido',
                resultado: 'fallido',
                request: $this,
                usuario: $usuario,
                correoIntentado: $correo,
                motivoFallo: 'Credenciales inválidas.'
            );

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
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

        // Registra el incidente de bloqueo temporal por fuerza bruta
        app(RegistrarAcceso::class)->registrar(
            evento: 'bloqueo_temporal',
            resultado: 'bloqueado',
            request: $this,
            correoIntentado: (string) $this->input('email'),
            motivoFallo: 'Demasiados intentos de inicio de sesión.',
            metadatos: [
                'segundos_restantes' => $seconds,
            ]
        );

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Construye la clave única de limitación de tasa basada en el correo en minúsculas y la IP del cliente.
     *
     * @return string Clave de limitación de tasa.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}

