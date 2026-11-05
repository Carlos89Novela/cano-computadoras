<?php

namespace App\Actions\Productos;

use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Auditoria\RegistrarAuditoria;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActualizarCategoriaProducto
{
    public function __construct(
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    public function ejecutar(
        Empresa $empresa,
        CategoriaProducto $categoria,
        User $actor,
        string $nombre,
        ?string $descripcion = null,
        ?Request $request = null
    ): CategoriaProducto {
        $nombre = trim($nombre);

        $descripcion = is_string($descripcion)
            ? trim($descripcion)
            : null;

        if ($descripcion === '') {
            $descripcion = null;
        }

        $this->validar(
            empresa: $empresa,
            categoria: $categoria,
            actor: $actor,
            nombre: $nombre,
            descripcion: $descripcion
        );

        $request ??= request();

        return DB::transaction(
            function () use (
                $empresa,
                $categoria,
                $actor,
                $nombre,
                $descripcion,
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
                    nombre: $nombre,
                    descripcion: $descripcion
                );

                $nombreDuplicado = CategoriaProducto::query()
                    ->where(
                        'empresa_id',
                        $empresaBloqueada->id
                    )
                    ->whereKeyNot(
                        $categoriaBloqueada->id
                    )
                    ->whereRaw(
                        'LOWER(nombre) = ?',
                        [
                            mb_strtolower($nombre),
                        ]
                    )
                    ->exists();

                if ($nombreDuplicado) {
                    throw ValidationException::withMessages([
                        'nombre' => 'Ya existe una categoría con ese nombre.',
                    ]);
                }

                $valoresAnteriores = [
                    'nombre' => $categoriaBloqueada->nombre,
                    'descripcion' => $categoriaBloqueada->descripcion,
                ];

                $valoresNuevos = [
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                ];

                if ($valoresAnteriores === $valoresNuevos) {
                    throw ValidationException::withMessages([
                        'nombre' => 'Debes modificar el nombre o la descripción.',
                    ]);
                }

                $categoriaBloqueada->update([
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'actualizado_por_id' => $actor->id,
                ]);

                $this->registrarAuditoria->registrar(
                    accion: 'categoria_producto.actualizada',
                    modulo: 'productos',
                    descripcion: 'Se actualizó una categoría de productos.',
                    actor: $actor,
                    modelo: $categoriaBloqueada,
                    valoresAnteriores: $valoresAnteriores,
                    valoresNuevos: $valoresNuevos,
                    metadatos: [
                        'empresa_id' => $empresaBloqueada->id,
                        'empresa_nombre' => $empresaBloqueada->nombre,
                    ],
                    request: $request
                );

                return $categoriaBloqueada
                    ->refresh()
                    ->load([
                        'empresa',
                        'creadoPor',
                        'actualizadoPor',
                    ]);
            }
        );
    }

    private function validar(
        Empresa $empresa,
        CategoriaProducto $categoria,
        User $actor,
        string $nombre,
        ?string $descripcion
    ): void {
        if (! $actor->can('productos.actualizar')) {
            throw new AuthorizationException(
                'No tienes permiso para actualizar categorías de productos.'
            );
        }

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se pueden actualizar categorías de una empresa inactiva.',
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

        if ($nombre === '') {
            throw ValidationException::withMessages([
                'nombre' => 'El nombre de la categoría es obligatorio.',
            ]);
        }

        if (mb_strlen($nombre) > 150) {
            throw ValidationException::withMessages([
                'nombre' => 'El nombre no puede superar los 150 caracteres.',
            ]);
        }

        if (
            $descripcion !== null
            && mb_strlen($descripcion) > 2000
        ) {
            throw ValidationException::withMessages([
                'descripcion' => 'La descripción no puede superar los 2000 caracteres.',
            ]);
        }
    }
}
