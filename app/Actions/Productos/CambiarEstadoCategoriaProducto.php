<?php

namespace App\Actions\Productos;

use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Auditoria\RegistrarAuditoria;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CambiarEstadoCategoriaProducto
{
    public function __construct(
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    public function ejecutar(
        Empresa $empresa,
        CategoriaProducto $categoria,
        User $actor,
        bool $activar,
        string $motivo,
        ?Request $request = null
    ): CategoriaProducto {
        $motivo = trim($motivo);

        $this->validar(
            empresa: $empresa,
            categoria: $categoria,
            actor: $actor,
            activar: $activar,
            motivo: $motivo
        );

        $request ??= request();

        return DB::transaction(
            function () use (
                $empresa,
                $categoria,
                $actor,
                $activar,
                $motivo,
                $request
            ): CategoriaProducto {
                $empresaBloqueada = Empresa::query()
                    ->lockForUpdate()
                    ->findOrFail($empresa->id);

                $categoriaBloqueada = CategoriaProducto::query()
                    ->lockForUpdate()
                    ->findOrFail($categoria->id);

                $this->validar(
                    empresa: $empresaBloqueada,
                    categoria: $categoriaBloqueada,
                    actor: $actor,
                    activar: $activar,
                    motivo: $motivo
                );

                $valoresAnteriores = [
                    'activo' => $categoriaBloqueada->activo,
                    'desactivado_por_id' => $categoriaBloqueada->desactivado_por_id,
                    'desactivado_at' => $this->fechaIso(
                        $categoriaBloqueada->desactivado_at
                    ),
                    'motivo_desactivacion' => $categoriaBloqueada
                        ->motivo_desactivacion,
                ];

                $categoriaBloqueada->update([
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

                $categoriaBloqueada->refresh();

                $valoresNuevos = [
                    'activo' => $categoriaBloqueada->activo,
                    'desactivado_por_id' => $categoriaBloqueada->desactivado_por_id,
                    'desactivado_at' => $this->fechaIso(
                        $categoriaBloqueada->desactivado_at
                    ),
                    'motivo_desactivacion' => $categoriaBloqueada
                        ->motivo_desactivacion,
                ];

                $this->registrarAuditoria->registrar(
                    accion: $activar
                        ? 'categoria_producto.reactivada'
                        : 'categoria_producto.desactivada',
                    modulo: 'productos',
                    descripcion: $activar
                        ? 'Se reactivó una categoría de productos.'
                        : 'Se desactivó una categoría de productos.',
                    actor: $actor,
                    modelo: $categoriaBloqueada,
                    valoresAnteriores: $valoresAnteriores,
                    valoresNuevos: $valoresNuevos,
                    metadatos: [
                        'empresa_id' => $empresaBloqueada->id,
                        'empresa_nombre' => $empresaBloqueada->nombre,
                        'categoria_nombre' => $categoriaBloqueada->nombre,
                    ],
                    motivo: $motivo,
                    request: $request
                );

                return $categoriaBloqueada->load([
                    'empresa',
                    'creadoPor',
                    'actualizadoPor',
                    'desactivadoPor',
                ]);
            }
        );
    }

    private function validar(
        Empresa $empresa,
        CategoriaProducto $categoria,
        User $actor,
        bool $activar,
        string $motivo
    ): void {
        if (
            ! $actor->can(
                'productos.cambiar_estado'
            )
        ) {
            throw new AuthorizationException(
                'No tienes permiso para cambiar el estado de categorías de productos.'
            );
        }

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se puede cambiar el estado de categorías de una empresa inactiva.',
            ]);
        }

        if (
            (int) $categoria->empresa_id
            !== (int) $empresa->id
        ) {
            throw new AuthorizationException(
                'La categoría no pertenece a la empresa indicada.'
            );
        }

        if ($categoria->activo === $activar) {
            throw ValidationException::withMessages([
                'activo' => $activar
                    ? 'La categoría ya se encuentra activa.'
                    : 'La categoría ya se encuentra inactiva.',
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
