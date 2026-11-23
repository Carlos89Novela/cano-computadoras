<?php

namespace App\Actions\Usuarios;

use App\Models\SuplenciaRol;
use App\Models\User;
use App\Services\Auditoria\RegistrarAuditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Acción de Seguridad y Operación: Activar Suplencia Temporal de Supervisor.
 *
 * Promueve temporalmente a un colaborador al rol `supervisor` durante vacaciones,
 * incapacidad o ausencia imprevista del supervisor titular, resguardando su
 * `rol_anterior` para restaurarlo al finalizar la suplencia.
 */
class ActivarSuplenciaSupervisor
{
    public function __construct(
        private CambiarRolUsuario $cambiarRolUsuario,
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    /**
     * Ejecuta la promoción temporal a supervisor y crea el registro de suplencia activa.
     *
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(
        User $actor,
        User $suplente,
        array $datos,
        ?Request $request = null
    ): SuplenciaRol {
        $request ??= request();

        $rolActual = $suplente->getRoleNames()->first();
        if (! is_string($rolActual) || $rolActual === '') {
            throw ValidationException::withMessages([
                'suplente_id' => 'El usuario seleccionado no tiene un rol base asignado para respaldar.',
            ]);
        }

        if ($rolActual === 'supervisor' || $rolActual === 'administrador') {
            throw ValidationException::withMessages([
                'suplente_id' => 'El usuario seleccionado ya cuenta con rol de supervisor o administrador.',
            ]);
        }

        $tieneSuplenciaActiva = SuplenciaRol::query()
            ->activas()
            ->where('suplente_id', $suplente->id)
            ->exists();

        if ($tieneSuplenciaActiva) {
            throw ValidationException::withMessages([
                'suplente_id' => 'Este colaborador ya tiene una suplencia temporal activa.',
            ]);
        }

        $motivo = trim((string) ($datos['motivo'] ?? 'Suplencia temporal de supervisión'));
        $supervisorAusenteId = ! empty($datos['supervisor_ausente_id'])
            ? (int) $datos['supervisor_ausente_id']
            : null;

        if ($supervisorAusenteId !== null && $supervisorAusenteId === (int) $suplente->id) {
            throw ValidationException::withMessages([
                'supervisor_ausente_id' => 'El supervisor ausente y el suplente no pueden ser la misma persona.',
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $suplente,
            $rolActual,
            $motivo,
            $supervisorAusenteId,
            $datos,
            $request
        ): SuplenciaRol {
            // 1. Cambiar rol mediante la acción oficial de seguridad (preserva permisos directos y audita)
            $this->cambiarRolUsuario->ejecutar(
                actor: $actor,
                usuario: $suplente,
                nuevoRol: 'supervisor',
                motivo: "Suplencia temporal de supervisor ({$motivo}) - Rol respaldado: {$rolActual}",
                request: $request
            );

            // 2. Registrar la suplencia activa para poder revertirla al regresar el supervisor titular
            $suplencia = SuplenciaRol::query()->create([
                'empresa_id' => ! empty($datos['empresa_id']) ? (int) $datos['empresa_id'] : null,
                'supervisor_ausente_id' => $supervisorAusenteId,
                'suplente_id' => $suplente->id,
                'rol_anterior' => $rolActual,
                'rol_temporal' => 'supervisor',
                'motivo' => $motivo,
                'fecha_inicio' => ! empty($datos['fecha_inicio'])
                    ? Carbon::parse((string) $datos['fecha_inicio'])->toDateString()
                    : now()->toDateString(),
                'fecha_fin_estimada' => ! empty($datos['fecha_fin_estimada'])
                    ? Carbon::parse((string) $datos['fecha_fin_estimada'])->toDateString()
                    : null,
                'estado' => 'activa',
                'autorizado_por_id' => $actor->id,
            ]);

            $this->registrarAuditoria->registrar(
                accion: 'usuario.suplencia_supervisor_activada',
                modulo: 'usuarios',
                descripcion: "Se activó suplencia temporal de supervisor para {$suplente->name}.",
                actor: $actor,
                modelo: $suplencia,
                usuarioAfectado: $suplente,
                valoresAnteriores: ['rol' => $rolActual],
                valoresNuevos: ['rol' => 'supervisor', 'suplencia_id' => $suplencia->id],
                motivo: $motivo,
                request: $request
            );

            return $suplencia->load(['suplente', 'supervisorAusente', 'autorizadoPor']);
        });
    }
}