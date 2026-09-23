<?php

namespace App\Services\Auditoria;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class RegistrarAuditoria
{
    /**
     * @var array<int, string>
     */
    private const CAMPOS_SENSIBLES = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        '_token',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'cookie',
        'authorization',
    ];

    /**
     * @param  array<string, mixed>|null  $valoresAnteriores
     * @param  array<string, mixed>|null  $valoresNuevos
     * @param  array<string, mixed>|null  $metadatos
     */
    public function registrar(
        string $accion,
        string $modulo,
        string $descripcion,
        ?User $actor = null,
        ?Model $modelo = null,
        ?User $usuarioAfectado = null,
        ?array $valoresAnteriores = null,
        ?array $valoresNuevos = null,
        ?array $metadatos = null,
        ?string $motivo = null,
        string $resultado = 'exitoso',
        ?Request $request = null
    ): Auditoria {
        $request ??= request();

        return Auditoria::query()->create([
            'usuario_id' => $actor?->id,
            'usuario_afectado_id' => $usuarioAfectado?->id,
            'sesion_id' => $this->obtenerSesionId($request),
            'accion' => $accion,
            'modulo' => $modulo,
            'modelo_tipo' => $modelo !== null
                ? $modelo::class
                : null,
            'modelo_id' => $modelo?->getKey(),
            'descripcion' => $descripcion,
            'valores_anteriores' => $this->filtrar(
                $valoresAnteriores
            ),
            'valores_nuevos' => $this->filtrar(
                $valoresNuevos
            ),
            'metadatos' => $this->filtrar($metadatos),
            'motivo' => $motivo,
            'direccion_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'ruta' => $request->path(),
            'metodo_http' => $request->method(),
            'resultado' => $resultado,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $datos
     * @return array<string, mixed>|null
     */
    private function filtrar(
        ?array $datos
    ): ?array {
        if ($datos === null) {
            return null;
        }

        return Arr::except(
            $datos,
            self::CAMPOS_SENSIBLES
        );
    }

    private function obtenerSesionId(
        Request $request
    ): ?string {
        if (! $request->hasSession()) {
            return null;
        }

        return $request->session()->getId();
    }
}
