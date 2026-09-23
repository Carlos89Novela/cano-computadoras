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

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (
            ! Auth::attempt(
                $this->only('email', 'password'),
                $this->boolean('remember')
            )
        ) {
            RateLimiter::hit($this->throttleKey());

            $correo = (string) $this->input('email');

            $usuario = User::query()
                ->where('email', $correo)
                ->first();

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

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

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
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
