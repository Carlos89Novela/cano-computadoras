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

class CrearCategoriaProducto
{
    public function __construct(
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    public function ejecutar(
        Empresa $empresa,
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
            actor: $actor,
            nombre: $nombre,
            descripcion: $descripcion
        );

        $request ??= request();

        return DB::transaction(
            function () use (
                $empresa,
                $actor,
                $nombre,
                $descripcion,
                $request
            ): CategoriaProducto {
                $empresaBloqueada = Empresa::query()
                    ->lockForUpdate()
                    ->findOrFail($empresa->id);

                $this->validar(
                    empresa: $empresaBloqueada,
                    actor: $actor,
                    nombre: $nombre,
                    descripcion: $descripcion
                );

                $nombreDuplicado = CategoriaProducto::query()
                    ->where(
                        'empresa_id',
                        $empresaBloqueada->id
                    )
                    ->whereRaw(
                        'LOWER(nombre) = ?',
                        [mb_strtolower($nombre)]
                    )
                    ->exists();

                if ($nombreDuplicado) {
                    throw ValidationException::withMessages([
                        'nombre' => 'Ya existe una categoría con ese nombre.',
                    ]);
                }

                $categoria = CategoriaProducto::query()->create([
                    'empresa_id' => $empresaBloqueada->id,
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'activo' => true,
                    'creado_por_id' => $actor->id,
                ]);

                $this->registrarAuditoria->registrar(
                    accion: 'categoria_producto.creada',
                    modulo: 'productos',
                    descripcion: 'Se creó una categoría de productos.',
                    actor: $actor,
                    modelo: $categoria,
                    valoresNuevos: [
                        'empresa_id' => $empresaBloqueada->id,
                        'categoria_id' => $categoria->id,
                        'nombre' => $categoria->nombre,
                        'descripcion' => $categoria->descripcion,
                        'activo' => $categoria->activo,
                    ],
                    metadatos: [
                        'empresa_nombre' => $empresaBloqueada->nombre,
                    ],
                    request: $request
                );

                return $categoria
                    ->fresh()
                    ->load([
                        'empresa',
                        'creadoPor',
                    ]);
            }
        );
    }

    private function validar(
        Empresa $empresa,
        User $actor,
        string $nombre,
        ?string $descripcion
    ): void {
        if (! $actor->can('productos.crear')) {
            throw new AuthorizationException(
                'No tienes permiso para crear categorías de productos.'
            );
        }

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se pueden crear categorías para una empresa inactiva.',
            ]);
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
