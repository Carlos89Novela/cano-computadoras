<?php

namespace App\Services\Auditoria;

use App\Models\RegistroAcceso;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Servicio especializado en el registro y auditoría de eventos de autenticación.
 *
 * Se ejecuta en eventos de:
 * - Login exitoso o fallido.
 * - Logout / Cierre de sesión.
 * - Bloqueos temporales por intentos reiterados (Throttling / Rate-Limiting).
 *
 * Realiza una doble persistencia:
 * 1. Inserta en la tabla 'registros_acceso' para métricas de seguridad y bloqueos.
 * 2. Emite un evento en la tabla 'auditorias' mediante RegistrarAuditoria para consolidar la bitácora general.
 */
class RegistrarAcceso
{
    /**
     * @param  RegistrarAuditoria  $registrarAuditoria  Servicio general de auditoría.
     */
    public function __construct(
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    /**
     * Registra un evento de acceso y su correspondiente entrada en la auditoría general.
     *
     * @param  string  $evento  Clave del evento (inicio_exitoso, inicio_fallido, cierre_sesion, bloqueo_temporal).
     * @param  string  $resultado  Resultado (exitoso, fallido).
     * @param  Request  $request  Petición HTTP actual de donde se extraen IP, agente y sesión.
     * @param  User|null  $usuario  Usuario autenticado (si se logró identificar).
     * @param  string|null  $correoIntentado  Correo que se escribió en el input (útil en logins fallidos).
     * @param  string|null  $motivoFallo  Causa del rechazo en caso de falla.
     * @param  array<string, mixed>|null  $metadatos  Información técnica adicional en JSON.
     * @return RegistroAcceso Registro persistido en base de datos.
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
        // Normaliza el correo intentado a minúsculas y sin espacios
        $correoNormalizado = $correoIntentado !== null
            ? Str::lower(trim($correoIntentado))
            : $usuario?->email;

        // 1. Inserta en la tabla especializada de accesos
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

        // 2. Inserta en la bitácora transversal de auditoría
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

    /**
     * Mapea el evento de acceso a una descripción legible para humanos.
     */
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
