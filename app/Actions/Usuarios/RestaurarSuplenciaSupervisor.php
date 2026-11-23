<?php

namespace App\Actions\Usuarios;

use App\Models\SuplenciaRol;
use App\Models\User;
use App\Services\Auditoria\RegistrarAuditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Acción de Seguridad y Operación: Restaurar Rol Anterior tras Suplencia.
 *
 * Devuelve al colaborador que cubrió temporalmente la supervisión a su
 * `rol_anterior` exacto en cuanto regresa el supervisor titular.
 */
class RestaurarSuplenciaSupervisor
{
    public function __construct(
        private CambiarRolUsuario $cambiarRolUsuario,
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    /**
     * Finaliza la suplencia activa y restaura el rol previo del usuario.
     */
    public function ejecutar(
        User $actor,
        SuplenciaRol $suplencia,
        ?string $notaCierre = null,
        ?Request $request = null
    ): SuplenciaRol {
        $request ??= request();

        if ($suplencia->estado !== 'activa') {
            throw ValidationException::withMessages([
                'suplencia' => 'Esta suplencia ya fue cerrada o restaurada anteriormente.',
            ]);
        }

        $motivoCierre = trim((string) ($notaCierre ?: 'Regreso del supervisor titular; se restaura rol anterior.'));

        return DB::transaction(function () use ($actor, $suplencia, $motivoCierre, $request): SuplenciaRol {
            /** @var User $suplente */
            $suplente = User::query()->findOrFail($suplencia->suplente_id);

            // 1. Devolver al colaborador exactamente a su rol anterior
            $this->cambiarRolUsuario->ejecutar(
                actor: $actor,
                usuario: $suplente,
                nuevoRol: $suplencia->rol_anterior,
                motivo: "Fin de suplencia de supervisor ({$motivoCierre}) - Se restaura rol: {$suplencia->rol_anterior}",
                request: $request
            );

            // 2. Marcar suplencia como restaurada
            $suplencia->update([
                'estado' => 'restaurada',
                'restaurado_por_id' => $actor->id,
                'restaurado_at' => now(),
            ]);

            $this->registrarAuditoria->registrar(
                accion: 'usuario.suplencia_supervisor_restaurada',
                modulo: 'usuarios',
                descripcion: "Se restauró el rol '{$suplencia->rol_anterior}' para {$suplente->name} al concluir suplencia.",
                actor: $actor,
                modelo: $suplencia,
                usuarioAfectado: $suplente,
                valoresAnteriores: ['rol' => $suplencia->rol_temporal, 'estado' => 'activa'],
                valoresNuevos: ['rol' => $suplencia->rol_anterior, 'estado' => 'restaurada'],
                motivo: $motivoCierre,
                request: $request
            );

            return $suplencia->fresh(['suplente', 'supervisorAusente', 'restauradoPor']);
        });
    }
}