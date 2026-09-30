<?php

namespace App\Actions\Productos;

// ---------------------------------------------------------
// MODELOS
// Entidades relacionadas con la actualización del producto.
// ---------------------------------------------------------

use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
// ---------------------------------------------------------
// SERVICIOS Y UTILIDADES
// Auditoría, autorización, transacciones y normalización.
// ---------------------------------------------------------

use App\Services\Auditoria\RegistrarAuditoria;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActualizarProducto
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
    // Actualiza el catálogo general del producto dentro de
    // una transacción y registra los cambios en auditoría.
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(
        Empresa $empresa,
        Producto $producto,
        CategoriaProducto $categoria,
        ?Proveedor $proveedor,
        User $actor,
        array $datos,
        ?Request $request = null
    ): Producto {
        $datosNormalizados = $this->normalizarDatos(
            $datos
        );

        $this->validar(
            empresa: $empresa,
            producto: $producto,
            categoria: $categoria,
            proveedor: $proveedor,
            actor: $actor,
            datos: $datosNormalizados
        );

        $request ??= request();

        return DB::transaction(
            function () use (
                $empresa,
                $producto,
                $categoria,
                $proveedor,
                $actor,
                $datosNormalizados,
                $request
            ): Producto {
                // ---------------------------------------------
                // BLOQUEOS TRANSACCIONALES
                // Evitan cambios concurrentes mientras se
                // valida y actualiza el producto.
                // ---------------------------------------------

                $empresaBloqueada = Empresa::query()
                    ->lockForUpdate()
                    ->findOrFail($empresa->id);

                $productoBloqueado = Producto::query()
                    ->lockForUpdate()
                    ->findOrFail($producto->id);

                $categoriaBloqueada = CategoriaProducto::query()
                    ->lockForUpdate()
                    ->findOrFail($categoria->id);

                $proveedorBloqueado = $proveedor === null
                    ? null
                    : Proveedor::query()
                        ->lockForUpdate()
                        ->findOrFail($proveedor->id);

                $this->validar(
                    empresa: $empresaBloqueada,
                    producto: $productoBloqueado,
                    categoria: $categoriaBloqueada,
                    proveedor: $proveedorBloqueado,
                    actor: $actor,
                    datos: $datosNormalizados
                );

                // ---------------------------------------------
                // IDENTIFICADORES
                // Obtiene el SKU y código de barras ya
                // normalizados.
                // ---------------------------------------------

                $sku = (string) $datosNormalizados['sku'];

                $codigoBarras = $datosNormalizados[
                    'codigo_barras'
                ];

                // ---------------------------------------------
                // SKU DUPLICADO
                // Excluye al producto que se está editando.
                // ---------------------------------------------

                $skuDuplicado = Producto::query()
                    ->where(
                        'empresa_id',
                        $empresaBloqueada->id
                    )
                    ->whereKeyNot(
                        $productoBloqueado->id
                    )
                    ->whereRaw(
                        'LOWER(sku) = ?',
                        [
                            mb_strtolower($sku),
                        ]
                    )
                    ->exists();

                if ($skuDuplicado) {
                    throw ValidationException::withMessages([
                        'sku' => 'Ya existe un producto con ese SKU.',
                    ]);
                }

                // ---------------------------------------------
                // CÓDIGO DE BARRAS DUPLICADO
                // Solo se valida cuando el producto tiene código.
                // ---------------------------------------------

                if (is_string($codigoBarras)) {
                    $codigoDuplicado = Producto::query()
                        ->where(
                            'empresa_id',
                            $empresaBloqueada->id
                        )
                        ->whereKeyNot(
                            $productoBloqueado->id
                        )
                        ->where(
                            'codigo_barras',
                            $codigoBarras
                        )
                        ->exists();

                    if ($codigoDuplicado) {
                        throw ValidationException::withMessages([
                            'codigo_barras' => 'Ya existe un producto con ese código de barras.',
                        ]);
                    }
                }

                // ---------------------------------------------
                // VALORES ANTERIORES
                // Se normalizan los decimales para comparar
                // correctamente valores como 100 y 100.00.
                // ---------------------------------------------

                $valoresAnteriores = [
                    'categoria_producto_id' => $productoBloqueado
                        ->categoria_producto_id,
                    'proveedor_principal_id' => $productoBloqueado
                        ->proveedor_principal_id,
                    'sku' => $productoBloqueado->sku,
                    'codigo_barras' => $productoBloqueado->codigo_barras,
                    'nombre' => $productoBloqueado->nombre,
                    'descripcion' => $productoBloqueado->descripcion,
                    'marca' => $productoBloqueado->marca,
                    'modelo' => $productoBloqueado->modelo,
                    'unidad_medida' => $productoBloqueado->unidad_medida,
                    'costo_referencia' => $this->decimalParaComparar(
                        $productoBloqueado
                            ->costo_referencia
                    ),
                    'precio_base' => $this->decimalParaComparar(
                        $productoBloqueado->precio_base
                    ),
                ];

                // ---------------------------------------------
                // VALORES NUEVOS
                // Contiene solamente los campos editables del
                // catálogo de productos.
                // ---------------------------------------------

                $valoresNuevos = [
                    'categoria_producto_id' => $categoriaBloqueada->id,
                    'proveedor_principal_id' => $proveedorBloqueado?->id,
                    'sku' => $sku,
                    'codigo_barras' => $codigoBarras,
                    'nombre' => $datosNormalizados['nombre'],
                    'descripcion' => $datosNormalizados['descripcion'],
                    'marca' => $datosNormalizados['marca'],
                    'modelo' => $datosNormalizados['modelo'],
                    'unidad_medida' => $datosNormalizados['unidad_medida'],
                    'costo_referencia' => $this->decimalParaComparar(
                        $datosNormalizados[
                            'costo_referencia'
                        ]
                    ),
                    'precio_base' => $this->decimalParaComparar(
                        $datosNormalizados[
                            'precio_base'
                        ]
                    ),
                ];

                // ---------------------------------------------
                // CAMBIO REAL OBLIGATORIO
                // Evita auditorías y escrituras sin diferencias.
                // ---------------------------------------------

                if ($valoresAnteriores === $valoresNuevos) {
                    throw ValidationException::withMessages([
                        'nombre' => 'Debes modificar al menos un dato del producto.',
                    ]);
                }

                // ---------------------------------------------
                // ACTUALIZACIÓN
                // No modifica empresa, estado ni existencias.
                // ---------------------------------------------

                $productoBloqueado->update([
                    'categoria_producto_id' => $categoriaBloqueada->id,
                    'proveedor_principal_id' => $proveedorBloqueado?->id,
                    'sku' => $sku,
                    'codigo_barras' => $codigoBarras,
                    'nombre' => $datosNormalizados['nombre'],
                    'descripcion' => $datosNormalizados['descripcion'],
                    'marca' => $datosNormalizados['marca'],
                    'modelo' => $datosNormalizados['modelo'],
                    'unidad_medida' => $datosNormalizados['unidad_medida'],
                    'costo_referencia' => $valoresNuevos['costo_referencia'],
                    'precio_base' => $valoresNuevos['precio_base'],
                    'actualizado_por_id' => $actor->id,
                ]);

                // ---------------------------------------------
                // AUDITORÍA
                // Registra los valores anteriores y nuevos.
                // ---------------------------------------------

                $this->registrarAuditoria->registrar(
                    accion: 'producto.actualizado',
                    modulo: 'productos',
                    descripcion: 'Se actualizaron los datos de un producto.',
                    actor: $actor,
                    modelo: $productoBloqueado,
                    valoresAnteriores: $valoresAnteriores,
                    valoresNuevos: $valoresNuevos,
                    metadatos: [
                        'empresa_id' => $empresaBloqueada->id,
                        'empresa_nombre' => $empresaBloqueada->nombre,
                        'categoria_nombre' => $categoriaBloqueada->nombre,
                        'proveedor_nombre' => $proveedorBloqueado?->nombre,
                    ],
                    request: $request
                );

                // ---------------------------------------------
                // RESULTADO
                // Devuelve el producto actualizado con sus
                // relaciones principales.
                // ---------------------------------------------

                return $productoBloqueado
                    ->refresh()
                    ->load([
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
    // NORMALIZACIÓN
    // Limpia textos, SKU, unidad y valores decimales.
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizarDatos(
        array $datos
    ): array {
        $sku = $datos['sku'] ?? '';
        $nombre = $datos['nombre'] ?? '';
        $unidadMedida = $datos['unidad_medida']
            ?? 'pieza';

        return [
            'sku' => is_string($sku)
                ? Str::upper(trim($sku))
                : '',
            'codigo_barras' => $this->textoOpcional(
                $datos['codigo_barras'] ?? null
            ),
            'nombre' => is_string($nombre)
                ? trim($nombre)
                : '',
            'descripcion' => $this->textoOpcional(
                $datos['descripcion'] ?? null
            ),
            'marca' => $this->textoOpcional(
                $datos['marca'] ?? null
            ),
            'modelo' => $this->textoOpcional(
                $datos['modelo'] ?? null
            ),
            'unidad_medida' => is_string($unidadMedida)
                    ? Str::lower(trim($unidadMedida))
                    : '',
            'costo_referencia' => $this->normalizarDecimal(
                $datos['costo_referencia']
                ?? null
            ),
            'precio_base' => $this->normalizarDecimal(
                $datos['precio_base']
                ?? null
            ),
        ];
    }

    // ---------------------------------------------------------
    // TEXTO OPCIONAL
    // Convierte cadenas vacías a null.
    // ---------------------------------------------------------

    private function textoOpcional(
        mixed $valor
    ): ?string {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return $valor !== ''
            ? $valor
            : null;
    }

    // ---------------------------------------------------------
    // DECIMAL OPCIONAL
    // Convierte cadenas vacías en null.
    // ---------------------------------------------------------

    private function normalizarDecimal(
        mixed $valor
    ): mixed {
        if (is_string($valor)) {
            $valor = trim($valor);

            if ($valor === '') {
                return null;
            }
        }

        return $valor;
    }

    // ---------------------------------------------------------
    // COMPARACIÓN DECIMAL
    // Devuelve una cadena con dos decimales para evitar
    // diferencias falsas entre 100, 100.0 y 100.00.
    // ---------------------------------------------------------

    private function decimalParaComparar(
        mixed $valor
    ): ?string {
        if ($valor === null || $valor === '') {
            return null;
        }

        return number_format(
            (float) $valor,
            2,
            '.',
            ''
        );
    }

    // ---------------------------------------------------------
    // VALIDACIONES DE NEGOCIO
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validar(
        Empresa $empresa,
        Producto $producto,
        CategoriaProducto $categoria,
        ?Proveedor $proveedor,
        User $actor,
        array $datos
    ): void {
        if (! $actor->can('productos.actualizar')) {
            throw new AuthorizationException(
                'No tienes permiso para actualizar productos.'
            );
        }

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se pueden actualizar productos de una empresa inactiva.',
            ]);
        }

        if (
            (int) $producto->empresa_id
            !== (int) $empresa->id
        ) {
            throw new AuthorizationException(
                'El producto no pertenece a la empresa indicada.'
            );
        }

        if (
            (int) $categoria->empresa_id
            !== (int) $empresa->id
        ) {
            throw new AuthorizationException(
                'La categoría no pertenece a la empresa indicada.'
            );
        }

        if (! $categoria->activo) {
            throw ValidationException::withMessages([
                'categoria_producto_id' => 'La categoría seleccionada se encuentra inactiva.',
            ]);
        }

        if ($proveedor !== null) {
            if (
                (int) $proveedor->empresa_id
                !== (int) $empresa->id
            ) {
                throw new AuthorizationException(
                    'El proveedor no pertenece a la empresa indicada.'
                );
            }

            if (! $proveedor->activo) {
                throw ValidationException::withMessages([
                    'proveedor_principal_id' => 'El proveedor seleccionado se encuentra inactivo.',
                ]);
            }
        }

        $this->validarDatosCatalogo($datos);
    }

    // ---------------------------------------------------------
    // VALIDACIÓN DE LOS CAMPOS DEL CATÁLOGO
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validarDatosCatalogo(
        array $datos
    ): void {
        $sku = $datos['sku'] ?? '';

        if (
            ! is_string($sku)
            || $sku === ''
            || mb_strlen($sku) > 80
            || preg_match(
                '/^[A-Z0-9._-]+$/',
                $sku
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                'sku' => 'El SKU debe contener letras, números, puntos, guiones o guiones bajos y no superar 80 caracteres.',
            ]);
        }

        $codigoBarras = $datos[
            'codigo_barras'
        ] ?? null;

        if (
            $codigoBarras !== null
            && (
                ! is_string($codigoBarras)
                || mb_strlen($codigoBarras) > 100
            )
        ) {
            throw ValidationException::withMessages([
                'codigo_barras' => 'El código de barras no puede superar 100 caracteres.',
            ]);
        }

        $nombre = $datos['nombre'] ?? '';

        if (
            ! is_string($nombre)
            || $nombre === ''
            || mb_strlen($nombre) > 200
        ) {
            throw ValidationException::withMessages([
                'nombre' => 'El nombre del producto es obligatorio y no puede superar 200 caracteres.',
            ]);
        }

        $this->validarLongitudOpcional(
            datos: $datos,
            campo: 'descripcion',
            maximo: 4000,
            mensaje: 'La descripción no puede superar 4000 caracteres.'
        );

        $this->validarLongitudOpcional(
            datos: $datos,
            campo: 'marca',
            maximo: 120,
            mensaje: 'La marca no puede superar 120 caracteres.'
        );

        $this->validarLongitudOpcional(
            datos: $datos,
            campo: 'modelo',
            maximo: 120,
            mensaje: 'El modelo no puede superar 120 caracteres.'
        );

        $unidadMedida = $datos[
            'unidad_medida'
        ] ?? '';

        $unidadesPermitidas = config(
            'access_control.unidades_medida_productos',
            []
        );

        if (
            ! is_string($unidadMedida)
            || ! is_array($unidadesPermitidas)
            || ! in_array(
                $unidadMedida,
                $unidadesPermitidas,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'unidad_medida' => 'La unidad de medida seleccionada no es válida.',
            ]);
        }

        $this->validarDecimalOpcional(
            datos: $datos,
            campo: 'costo_referencia',
            mensaje: 'El costo de referencia debe ser un número válido entre 0 y 9999999999.99.'
        );

        $this->validarDecimalOpcional(
            datos: $datos,
            campo: 'precio_base',
            mensaje: 'El precio base debe ser un número válido entre 0 y 9999999999.99.'
        );
    }

    // ---------------------------------------------------------
    // LONGITUD DE TEXTO OPCIONAL
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validarLongitudOpcional(
        array $datos,
        string $campo,
        int $maximo,
        string $mensaje
    ): void {
        $valor = $datos[$campo] ?? null;

        if ($valor === null) {
            return;
        }

        if (
            ! is_string($valor)
            || mb_strlen($valor) > $maximo
        ) {
            throw ValidationException::withMessages([
                $campo => $mensaje,
            ]);
        }
    }

    // ---------------------------------------------------------
    // DECIMAL OPCIONAL
    // Compatible con columnas decimal(12, 2).
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validarDecimalOpcional(
        array $datos,
        string $campo,
        string $mensaje
    ): void {
        $valor = $datos[$campo] ?? null;

        if ($valor === null) {
            return;
        }

        if (
            ! is_numeric($valor)
            || ! is_finite((float) $valor)
            || (float) $valor < 0
            || (float) $valor > 9999999999.99
        ) {
            throw ValidationException::withMessages([
                $campo => $mensaje,
            ]);
        }
    }
}
