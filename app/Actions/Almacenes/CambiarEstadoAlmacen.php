<?php

namespace App\Actions\Almacenes;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Auditoria\RegistrarAuditoria;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CambiarEstadoAlmacen
{
    // ---------------------------------------------------------
    // INYECCIÓN DE DEPENDENCIAS
    // Recibe el servicio centralizado encargado de registrar
    // la auditoría de los cambios de estado.
    // ---------------------------------------------------------

    public function __construct(
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    // ---------------------------------------------------------
    // CAMBIAR ESTADO DEL ALMACÉN
    // Desactiva o reactiva un almacén físico conservando su
    // información, relaciones e historial operativo.
    // ---------------------------------------------------------

    public function ejecutar(
        Empresa $empresa,
        Almacen $almacen,
        User $actor,
        bool $activar,
        string $motivo,
        ?Request $request = null
    ): Almacen {
        // -----------------------------------------------------
        // NORMALIZACIÓN DEL MOTIVO
        // Elimina espacios innecesarios antes de validar y
        // guardar la justificación de la operación.
        // -----------------------------------------------------

        $motivoNormalizado = trim($motivo);

        // -----------------------------------------------------
        // VALIDACIÓN PRELIMINAR
        // Rechaza operaciones inválidas antes de iniciar la
        // transacción.
        // -----------------------------------------------------

        $this->validar(
            empresa: $empresa,
            almacen: $almacen,
            actor: $actor,
            activar: $activar,
            motivo: $motivoNormalizado
        );

        $request ??= request();

        // -----------------------------------------------------
        // TRANSACCIÓN
        // El cambio de estado y la auditoría constituyen una
        // sola operación atómica.
        // -----------------------------------------------------

        return DB::transaction(
            function () use (
                $empresa,
                $almacen,
                $actor,
                $activar,
                $motivoNormalizado,
                $request
            ): Almacen {
                // ---------------------------------------------
                // BLOQUEO DE EMPRESA Y ALMACÉN
                // Evita cambios concurrentes mientras se
                // modifica el estado operativo.
                // ---------------------------------------------

                $empresaBloqueada = Empresa::query()
                    ->lockForUpdate()
                    ->findOrFail($empresa->id);

                $almacenBloqueado = Almacen::query()
                    ->lockForUpdate()
                    ->findOrFail($almacen->id);

                // ---------------------------------------------
                // REVALIDACIÓN
                // Confirma nuevamente las reglas utilizando
                // los registros bloqueados.
                // ---------------------------------------------

                $this->validar(
                    empresa: $empresaBloqueada,
                    almacen: $almacenBloqueado,
                    actor: $actor,
                    activar: $activar,
                    motivo: $motivoNormalizado
                );

                // ---------------------------------------------
                // EXISTENCIAS PENDIENTES
                // Antes de desactivar, bloquea y comprueba que
                // no existan saldos físicos ni reservados.
                // Esta regla no impide la reactivación.
                // ---------------------------------------------

                if (! $activar) {
                    $tieneExistencias = $almacenBloqueado
                        ->existencias()
                        ->lockForUpdate()
                        ->where(function ($consulta): void {
                            $consulta
                                ->where(
                                    'cantidad',
                                    '!=',
                                    0
                                )
                                ->orWhere(
                                    'cantidad_reservada',
                                    '!=',
                                    0
                                );
                        })
                        ->exists();

                    if ($tieneExistencias) {
                        throw ValidationException::withMessages([
                            'almacen' => 'No se puede desactivar un almacén con existencias físicas o reservadas.',
                        ]);
                    }
                }

                // ---------------------------------------------
                // VALORES ANTERIORES
                // Conserva la fotografía del estado anterior
                // para la bitácora de auditoría.
                // ---------------------------------------------

                $valoresAnteriores = [
                    'activo' => $almacenBloqueado->activo,
                    'desactivado_por_id' => $almacenBloqueado
                        ->desactivado_por_id,
                    'desactivado_at' => $almacenBloqueado
                        ->desactivado_at,
                    'motivo_desactivacion' => $almacenBloqueado
                        ->motivo_desactivacion,
                ];

                // ---------------------------------------------
                // REACTIVACIÓN
                // Habilita nuevamente el almacén y limpia los
                // datos correspondientes a la desactivación.
                // ---------------------------------------------

                if ($activar) {
                    $almacenBloqueado->update([
                        'activo' => true,
                        'desactivado_por_id' => null,
                        'desactivado_at' => null,
                        'motivo_desactivacion' => null,
                    ]);

                    $accion = 'almacen.reactivado';

                    $descripcion =
                        'Se reactivó un almacén físico.';
                } else {
                    // -----------------------------------------
                    // DESACTIVACIÓN
                    // Deshabilita lógicamente el almacén y
                    // registra actor, fecha y justificación.
                    // -----------------------------------------

                    $almacenBloqueado->update([
                        'activo' => false,
                        'desactivado_por_id' => $actor->id,
                        'desactivado_at' => now(),
                        'motivo_desactivacion' => $motivoNormalizado,
                    ]);

                    $accion = 'almacen.desactivado';

                    $descripcion =
                        'Se desactivó un almacén físico.';
                }

                // ---------------------------------------------
                // RECARGAR ESTADO
                // Obtiene los valores persistidos para usarlos
                // como fotografía nueva de la auditoría.
                // ---------------------------------------------

                $almacenActualizado =
                    $almacenBloqueado->fresh();

                if ($almacenActualizado === null) {
                    throw ValidationException::withMessages([
                        'almacen' => 'No fue posible recuperar el almacén actualizado.',
                    ]);
                }

                $valoresNuevos = [
                    'activo' => $almacenActualizado->activo,
                    'desactivado_por_id' => $almacenActualizado
                        ->desactivado_por_id,
                    'desactivado_at' => $almacenActualizado
                        ->desactivado_at,
                    'motivo_desactivacion' => $almacenActualizado
                        ->motivo_desactivacion,
                ];

                // ---------------------------------------------
                // AUDITORÍA
                // Registra el cambio de estado, sus valores
                // anteriores, nuevos y el motivo indicado.
                // ---------------------------------------------

                $this->registrarAuditoria->registrar(
                    accion: $accion,
                    modulo: 'almacenes',
                    descripcion: $descripcion,
                    actor: $actor,
                    modelo: $almacenActualizado,
                    valoresAnteriores: $valoresAnteriores,
                    valoresNuevos: $valoresNuevos,
                    metadatos: [
                        'empresa_id' => $empresaBloqueada->id,
                        'sucursal_id' => $almacenActualizado
                            ->sucursal_id,
                        'codigo' => $almacenActualizado->codigo,
                        'tipo' => $almacenActualizado->tipo,
                        'es_virtual' => $almacenActualizado
                            ->es_virtual,
                    ],
                    motivo: $motivoNormalizado,
                    request: $request
                );

                return $almacenActualizado->load([
                    'empresa',
                    'sucursal',
                ]);
            }
        );
    }

    // ---------------------------------------------------------
    // VALIDAR OPERACIÓN
    // Comprueba permiso, pertenencia empresarial, estado de
    // la empresa, cambio real y motivo de auditoría.
    // ---------------------------------------------------------

    private function validar(
        Empresa $empresa,
        Almacen $almacen,
        User $actor,
        bool $activar,
        string $motivo
    ): void {
        // -----------------------------------------------------
        // PERMISO
        // El actor necesita almacenes.cambiar_estado mediante
        // su rol o una asignación directa.
        // -----------------------------------------------------

        if (! $actor->can('almacenes.cambiar_estado')) {
            throw new AuthorizationException(
                'No tienes permiso para cambiar el estado de almacenes.'
            );
        }

        // -----------------------------------------------------
        // PERTENENCIA EMPRESARIAL
        // Impide administrar el almacén mediante una empresa
        // distinta de la propietaria.
        // -----------------------------------------------------

        if ($almacen->empresa_id !== $empresa->id) {
            throw new AuthorizationException(
                'El almacén no pertenece a la empresa indicada.'
            );
        }

        // -----------------------------------------------------
        // SUCURSAL OBLIGATORIA PARA ALMACENES FÍSICOS
        // Todo almacén físico debe permanecer asociado a una
        // sucursal válida.
        // -----------------------------------------------------

        if (
            ! $almacen->es_virtual
            && $almacen->sucursal_id === null
        ) {
            throw ValidationException::withMessages([
                'sucursal' => 'El almacén físico debe pertenecer a una sucursal.',
            ]);
        }

        // -----------------------------------------------------
        // PERTENENCIA DE LA SUCURSAL
        // Comprueba que la sucursal asociada pertenezca a la
        // misma empresa del almacén.
        // -----------------------------------------------------

        $sucursal = $almacen->sucursal;

        if (
            ! $almacen->es_virtual
            && $sucursal === null
        ) {
            throw ValidationException::withMessages([
                'sucursal' => 'No fue posible localizar la sucursal del almacén.',
            ]);
        }

        if (
            $sucursal !== null
            && $sucursal->empresa_id !== $empresa->id
        ) {
            throw new AuthorizationException(
                'La sucursal del almacén no pertenece a la empresa indicada.'
            );
        }

        // -----------------------------------------------------
        // SUCURSAL ACTIVA
        // Los almacenes físicos no pueden cambiar de estado
        // mientras su sucursal permanezca desactivada.
        // -----------------------------------------------------

        if (
            $sucursal !== null
            && ! $sucursal->activo
        ) {
            throw ValidationException::withMessages([
                'sucursal' => 'No se puede cambiar el estado de un almacén de una sucursal inactiva.',
            ]);
        }

        // -----------------------------------------------------
        // EMPRESA ACTIVA
        // No se permite cambiar el estado de los almacenes de
        // una empresa desactivada.
        // -----------------------------------------------------

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se puede cambiar el estado de almacenes de una empresa inactiva.',
            ]);
        }

        // -----------------------------------------------------
        // MOTIVO OBLIGATORIO
        // Toda desactivación o reactivación necesita una
        // justificación de hasta 1000 caracteres.
        // -----------------------------------------------------

        if (
            $motivo === ''
            || mb_strlen($motivo) > 1000
        ) {
            throw ValidationException::withMessages([
                'motivo' => 'Debes indicar un motivo válido de hasta 1000 caracteres.',
            ]);
        }

        // -----------------------------------------------------
        // ALMACÉN VIRTUAL O DE TRÁNSITO
        // El almacén común de tránsito es administrado por el
        // sistema y no admite cambios manuales de estado.
        // -----------------------------------------------------

        if (
            $almacen->tipo === Almacen::TIPO_TRANSITO
            || $almacen->es_virtual
        ) {
            throw ValidationException::withMessages([
                'almacen' => 'El almacén virtual de tránsito no puede cambiarse de estado manualmente.',
            ]);
        }

        // -----------------------------------------------------
        // ALMACÉN PRINCIPAL
        // Mientras una sucursal esté operativa, su almacén
        // principal debe permanecer activo.
        // -----------------------------------------------------

        if (
            ! $activar
            && $almacen->tipo === Almacen::TIPO_PRINCIPAL
        ) {
            throw ValidationException::withMessages([
                'almacen' => 'El almacén principal no puede desactivarse manualmente.',
            ]);
        }

        // -----------------------------------------------------
        // CAMBIO REAL
        // Evita activar un almacén activo o desactivar uno que
        // ya se encuentra desactivado.
        // -----------------------------------------------------

        if ($almacen->activo === $activar) {
            throw ValidationException::withMessages([
                'activo' => 'El almacén ya tiene el estado solicitado.',
            ]);
        }
    }
}
