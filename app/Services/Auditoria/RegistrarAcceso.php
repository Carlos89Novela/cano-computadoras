<?php

namespace App\Services\Auditoria;

use App\Models\RegistroAcceso;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RegistrarAcceso
{
    public function __construct(
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    /**
     * @param  array<string, mixed>|null  $metadatos
     */
    public function registrar(
        string $evento,
        string $resultado,
        Request $request,
        ?User $usuario = null,
        ?string $correoIntentado = null,
        ?string $motivoFallo = null,
        ?array $metadatos = null
    ): RegistroAcceso {
        $correoNormalizado = $correoIntentado !== null
            ? Str::lower(trim($correoIntentado))
            : $usuario?->email;

        $registro = RegistroAcceso::query()->create([
            'user_id' => $usuario?->id,
            'correo_intentado' => $correoNormalizado,
            'sesion_id' => $request->hasSession()
                ? $request->session()->getId()
                : null,
            'evento' => $evento,
            'resultado' => $resultado,
            'direccion_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'ruta' => $request->path(),
            'metodo_http' => $request->method(),
            'motivo_fallo' => $motivoFallo,
            'metadatos' => $metadatos,
        ]);

        $this->registrarAuditoria->registrar(
            accion: 'autenticacion.'.$evento,
            modulo: 'autenticacion',
            descripcion: $this->descripcion($evento),
            actor: $usuario,
            modelo: $registro,
            usuarioAfectado: $usuario,
            metadatos: [
                'registro_acceso_id' => $registro->id,
                'correo_intentado' => $correoNormalizado,
                'motivo_fallo' => $motivoFallo,
            ],
            resultado: $resultado,
            request: $request
        );

        return $registro;
    }

    private function descripcion(
        string $evento
    ): string {
        return match ($evento) {
            'inicio_exitoso' => 'Inicio de sesión exitoso.',

            'inicio_fallido' => 'Intento de inicio de sesión fallido.',

            'cierre_sesion' => 'Cierre de sesión exitoso.',

            'bloqueo_temporal' => 'Inicio de sesión bloqueado temporalmente.',

            default => 'Evento de autenticación registrado.',
        };
    }
}
