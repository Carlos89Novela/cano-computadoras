<?php

namespace App\Actions\Proveedores;

use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\Auditoria\RegistrarAuditoria;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Acción del dominio: CambiarEstadoProveedor.
 *
 * Administra el ciclo de vida operativo del proveedor mediante baja o reactivación lógica:
 * 1. Exige el permiso 'productos.cambiar_estado'.
 * 2. Verifica la pertenencia a la empresa activa.
 * 3. Impide transiciones redundantes (activar uno ya activo o desactivar uno ya inactivo).
 * 4. Exige una justificación o motivo formal ('motivo') obligatorio para garantizar trazabilidad.
 * 5. Si se desactiva, persiste: 'desactivado_por_id', 'desactivado_at' y 'motivo_desactivacion'.
 *    Si se reactiva, limpia dichos campos a null.
 * 6. Registra auditoría con acción 'proveedor.reactivado' o 'proveedor.desactivado'.
 */
class CambiarEstadoProveedor
{
    /**
     * @param  RegistrarAuditoria  $registrarAuditoria  Servicio de auditoría del sistema.
     */
    public function __construct(
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    /**
     * Ejecuta el cambio de estado (activar o desactivar).
     *
     * @param  Empresa  $empresa  Empresa a la que pertenece el proveedor.
     * @param  Proveedor  $proveedor  Proveedor cuyo estado se va a modificar.
     * @param  User  $actor  Usuario que autoriza y ejecuta el cambio.
     * @param  bool  $activar  true para reactivar, false para dar de baja lógica.
     * @param  string  $motivo  Justificación obligatoria del cambio.
     * @param  Request|null  $request  Petición HTTP actual para capturar metadatos en auditoría.
     * @return Proveedor Proveedor con el nuevo estado persistido y relaciones cargadas.
     *
     * @throws AuthorizationException Si el usuario no tiene permisos o hay conflicto de empresa.
     * @throws ValidationException Si el motivo está vacío o el proveedor ya está en el estado deseado.
     */
    public function ejecutar(
        Empresa $empresa,
        Proveedor $proveedor,
        User $actor,
        bool $activar,
        string $motivo,
        ?Request $request = null
    ): Proveedor {
        // Limpieza de espacios en blanco en la justificación
        $motivo = trim($motivo);

        // Validación inicial rápida antes de abrir la transacción
        $this->validar(
            empresa: $empresa,
            proveedor: $proveedor,
            actor: $actor,
            activar: $activar,
            motivo: $motivo
        );

        $request ??= request();

        // Transacción con bloqueo pesimista
        return DB::transaction(
            function () use (
                $empresa,
                $proveedor,
                $actor,
                $activar,
                $motivo,
                $request
            ): Proveedor {
                // Bloquea los registros de la empresa y del proveedor para evitar cambios concurrentes
                $empresaBloqueada = Empresa::query()
                    ->lockForUpdate()
                    ->findOrFail($empresa->id);

                $proveedorBloqueado = Proveedor::query()
                    ->lockForUpdate()
                    ->findOrFail($proveedor->id);

                // Re-valida con los datos bloqueados más recientes
                $this->validar(
                    empresa: $empresaBloqueada,
                    proveedor: $proveedorBloqueado,
                    actor: $actor,
                    activar: $activar,
                    motivo: $motivo
                );

                // Captura el estado anterior antes de la mutación
                $valoresAnteriores = [
                    'activo' => $proveedorBloqueado->activo,
                    'desactivado_por_id' => $proveedorBloqueado
                        ->desactivado_por_id,
                    'desactivado_at' => $this->fechaIso(
                        $proveedorBloqueado
                            ->desactivado_at
                    ),
                    'motivo_desactivacion' => $proveedorBloqueado
                        ->motivo_desactivacion,
                ];

                // Actualiza los campos según la transición solicitada:
                // - Si se activa: se limpian los campos de desactivación.
                // - Si se desactiva: se registran el usuario, fecha y motivo de baja.
                $proveedorBloqueado->update([
                    'activo' => $activar,
                    'actualizado_por_id' => $actor->id,
                    'desactivado_por_id' => $activar
                        ? null
                        : $actor->id,
                    'desactivado_at' => $activar
                        ? null
                        : now(),
                    'motivo_desactivacion' => $activar
                        ? null
                        : $motivo,
                ]);

                $proveedorBloqueado->refresh();

                // Captura el estado resultante para el registro de auditoría
                $valoresNuevos = [
                    'activo' => $proveedorBloqueado->activo,
                    'desactivado_por_id' => $proveedorBloqueado
                        ->desactivado_por_id,
                    'desactivado_at' => $this->fechaIso(
                        $proveedorBloqueado
                            ->desactivado_at
                    ),
                    'motivo_desactivacion' => $proveedorBloqueado
                        ->motivo_desactivacion,
                ];

                // Registra la auditoría del cambio de estado
                $this->registrarAuditoria->registrar(
                    accion: $activar
                        ? 'proveedor.reactivado'
                        : 'proveedor.desactivado',
                    modulo: 'proveedores',
                    descripcion: $activar
                        ? 'Se reactivó un proveedor.'
                        : 'Se desactivó un proveedor.',
                    actor: $actor,
                    modelo: $proveedorBloqueado,
                    valoresAnteriores: $valoresAnteriores,
                    valoresNuevos: $valoresNuevos,
                    metadatos: [
                        'empresa_id' => $empresaBloqueada->id,
                        'empresa_nombre' => $empresaBloqueada->nombre,
                        'proveedor_codigo' => $proveedorBloqueado->codigo,
                        'proveedor_nombre' => $proveedorBloqueado->nombre,
                    ],
                    motivo: $motivo,
                    request: $request
                );

                // Retorna el proveedor con sus relaciones para respuesta HTTP o vistas
                return $proveedorBloqueado->load([
                    'empresa',
                    'creadoPor',
                    'actualizadoPor',
                    'desactivadoPor',
                ]);
            }
        );
    }

    /**
     * Valida permisos, límites y consistencia lógica del cambio de estado.
     *
     * @throws AuthorizationException Si no tiene permisos o no coincide la empresa.
     * @throws ValidationException Si el motivo es inválido o el estado no cambia.
     */
    private function validar(
        Empresa $empresa,
        Proveedor $proveedor,
        User $actor,
        bool $activar,
        string $motivo
    ): void {
        // Valida permiso específico para cambiar estados de productos/proveedores
        if (
            ! $actor->can(
                'productos.cambiar_estado'
            )
        ) {
            throw new AuthorizationException(
                'No tienes permiso para cambiar el estado de proveedores.'
            );
        }

        // Una empresa inactiva tiene sus catálogos congelados
        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se puede cambiar el estado de proveedores de una empresa inactiva.',
            ]);
        }

        // Valida pertenencia multi-empresa
        if (
            (int) $proveedor->empresa_id
            !== (int) $empresa->id
        ) {
            throw new AuthorizationException(
                'El proveedor no pertenece a la empresa indicada.'
            );
        }

        // Evita transiciones redundantes (ej. reactivar un proveedor que ya está activo)
        if ($proveedor->activo === $activar) {
            throw ValidationException::withMessages([
                'activo' => $activar
                    ? 'El proveedor ya se encuentra activo.'
                    : 'El proveedor ya se encuentra inactivo.',
            ]);
        }

        // El motivo es obligatorio para que quede justificado en la auditoría
        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Debes indicar el motivo del cambio de estado.',
            ]);
        }

        // Límite de longitud para evitar textos desbordados en la base de datos
        if (mb_strlen($motivo) > 1000) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo no puede superar los 1000 caracteres.',
            ]);
        }
    }

    /**
     * Formatea fechas a formato ISO-8601 (ATOM) para almacenamiento estandarizado en JSON de auditoría.
     */
    private function fechaIso(
        mixed $valor
    ): ?string {
        if ($valor instanceof DateTimeInterface) {
            return $valor->format(DATE_ATOM);
        }

        if (is_string($valor) && $valor !== '') {
            return $valor;
        }

        return null;
    }
}
