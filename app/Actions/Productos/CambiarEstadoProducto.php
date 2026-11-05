<?php

namespace App\Actions\Productos;

// ---------------------------------------------------------
// MODELOS
// Entidades relacionadas con el cambio de estado del producto.
// ---------------------------------------------------------

use App\Models\Empresa;
use App\Models\Producto;
use App\Models\User;
// ---------------------------------------------------------
// SERVICIOS Y UTILIDADES
// Auditoría, autorización y transacciones con bloqueo.
// ---------------------------------------------------------

use App\Services\Auditoria\RegistrarAuditoria;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CambiarEstadoProducto
{
    // ---------------------------------------------------------
    // CONSTRUCTOR
    // Inyecta el servicio central de auditoría.
    // ---------------------------------------------------------

    public function __construct(
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    // ---------------------------------------------------------
    // MÉTODO PRINCIPAL
    // Modifica la disponibilidad del producto y persiste auditoría.
    // ---------------------------------------------------------

    public function ejecutar(
        Empresa $empresa,
        Producto $producto,
        User $actor,
        bool $activar,
        string $motivo,
        ?Request $request = null
    ): Producto {
        $motivo = trim($motivo);

        $this->validar(
            empresa: $empresa,
            producto: $producto,
            actor: $actor,
            activar: $activar,
            motivo: $motivo
        );

        $request ??= request();

        return DB::transaction(
            function () use (
                $empresa,
                $producto,
                $actor,
                $activar,
                $motivo,
                $request
            ): Producto {
                $empresaBloqueada = Empresa::query()
                    ->lockForUpdate()
                    ->findOrFail($empresa->id);

                $productoBloqueado = Producto::query()
                    ->lockForUpdate()
                    ->findOrFail($producto->id);

                $this->validar(
                    empresa: $empresaBloqueada,
                    producto: $productoBloqueado,
                    actor: $actor,
                    activar: $activar,
                    motivo: $motivo
                );

                $valoresAnteriores = [
                    'activo' => $productoBloqueado->activo,
                    'desactivado_por_id' => $productoBloqueado->desactivado_por_id,
                    'desactivado_at' => $this->fechaIso(
                        $productoBloqueado->desactivado_at
                    ),
                    'motivo_desactivacion' => $productoBloqueado->motivo_desactivacion,
                ];

                $productoBloqueado->update([
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

                $productoBloqueado->refresh();

                $valoresNuevos = [
                    'activo' => $productoBloqueado->activo,
                    'desactivado_por_id' => $productoBloqueado->desactivado_por_id,
                    'desactivado_at' => $this->fechaIso(
                        $productoBloqueado->desactivado_at
                    ),
                    'motivo_desactivacion' => $productoBloqueado->motivo_desactivacion,
                ];

                $this->registrarAuditoria->registrar(
                    accion: $activar
                        ? 'producto.reactivado'
                        : 'producto.desactivado',
                    modulo: 'productos',
                    descripcion: $activar
                        ? 'Se reactivó un producto en el catálogo.'
                        : 'Se desactivó un producto del catálogo.',
                    actor: $actor,
                    modelo: $productoBloqueado,
                    valoresAnteriores: $valoresAnteriores,
                    valoresNuevos: $valoresNuevos,
                    metadatos: [
                        'empresa_id' => $empresaBloqueada->id,
                        'empresa_nombre' => $empresaBloqueada->nombre,
                        'producto_sku' => $productoBloqueado->sku,
                        'producto_nombre' => $productoBloqueado->nombre,
                    ],
                    motivo: $motivo,
                    request: $request
                );

                return $productoBloqueado->load([
                    'empresa',
                    'categoria',
                    'proveedorPrincipal',
                    'creadoPor',
                    'actualizadoPor',
                    'desactivadoPor',
                ]);
            }
        );
    }

    // ---------------------------------------------------------
    // VALIDACIÓN
    // Comprueba permisos, pertenencia, estado actual y justificación.
    // ---------------------------------------------------------

    private function validar(
        Empresa $empresa,
        Producto $producto,
        User $actor,
        bool $activar,
        string $motivo
    ): void {
        if (
            ! $actor->can('productos.cambiar_estado')
        ) {
            throw new AuthorizationException(
                'No tienes permiso para cambiar el estado de productos.'
            );
        }

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se puede cambiar el estado de productos de una empresa inactiva.',
            ]);
        }

        if (
            (int) $producto->empresa_id !== (int) $empresa->id
        ) {
            throw new AuthorizationException(
                'El producto no pertenece a la empresa indicada.'
            );
        }

        if ($producto->activo === $activar) {
            throw ValidationException::withMessages([
                'activo' => $activar
                    ? 'El producto ya se encuentra activo.'
                    : 'El producto ya se encuentra inactivo.',
            ]);
        }

        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Debes indicar el motivo del cambio de estado.',
            ]);
        }

        if (mb_strlen($motivo) > 1000) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo no puede superar los 1000 caracteres.',
            ]);
        }
    }

    // ---------------------------------------------------------
    // FORMATO DE FECHA
    // Normaliza marcas de tiempo para auditoría.
    // ---------------------------------------------------------

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
