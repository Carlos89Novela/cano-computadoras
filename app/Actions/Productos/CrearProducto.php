<?php

namespace App\Actions\Productos;

// ---------------------------------------------------------
// MODELOS
// Entidades relacionadas con la creación del producto.
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

class CrearProducto
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
    // Valida, normaliza y crea el producto dentro de una
    // transacción. No crea existencias ni movimientos.
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(
        Empresa $empresa,
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
            categoria: $categoria,
            proveedor: $proveedor,
            actor: $actor,
            datos: $datosNormalizados
        );

        $request ??= request();

        return DB::transaction(
            function () use (
                $empresa,
                $categoria,
                $proveedor,
                $actor,
                $datosNormalizados,
                $request
            ): Producto {
                // ---------------------------------------------
                // BLOQUEOS TRANSACCIONALES
                // Evitan cambios simultáneos mientras se valida
                // y registra el nuevo producto.
                // ---------------------------------------------

                $empresaBloqueada = Empresa::query()
                    ->lockForUpdate()
                    ->findOrFail($empresa->id);

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
                    categoria: $categoriaBloqueada,
                    proveedor: $proveedorBloqueado,
                    actor: $actor,
                    datos: $datosNormalizados
                );

                // ---------------------------------------------
                // IDENTIFICADORES NORMALIZADOS
                // SKU es obligatorio. Código de barras es
                // opcional y se guarda como null si está vacío.
                // ---------------------------------------------

                $sku = (string) $datosNormalizados['sku'];

                $codigoBarras = $datosNormalizados[
                    'codigo_barras'
                ];

                // ---------------------------------------------
                // VALIDACIÓN DE SKU DUPLICADO
                // La comparación no distingue mayúsculas.
                // ---------------------------------------------

                $skuDuplicado = Producto::query()
                    ->where(
                        'empresa_id',
                        $empresaBloqueada->id
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
                // VALIDACIÓN DE CÓDIGO DE BARRAS DUPLICADO
                // Solo se ejecuta cuando existe un código.
                // ---------------------------------------------

                if (is_string($codigoBarras)) {
                    $codigoDuplicado = Producto::query()
                        ->where(
                            'empresa_id',
                            $empresaBloqueada->id
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
                // CONVERSIÓN DE PRECIOS
                // Los valores ya fueron validados como números
                // no negativos antes de llegar a este punto.
                // ---------------------------------------------

                $costoReferencia = $this->decimalOpcional(
                    $datosNormalizados[
                        'costo_referencia'
                    ]
                );

                $precioBase = $this->decimalOpcional(
                    $datosNormalizados[
                        'precio_base'
                    ]
                );

                // ---------------------------------------------
                // CREACIÓN DEL PRODUCTO
                // Registra solamente el catálogo general.
                // El stock se manejará por almacén posteriormente.
                // ---------------------------------------------

                $producto = Producto::query()->create([
                    'empresa_id' => $empresaBloqueada->id,
                    'categoria_producto_id' => $categoriaBloqueada->id,
                    'proveedor_principal_id' => $proveedorBloqueado?->id,
                    'sku' => $sku,
                    'codigo_barras' => $codigoBarras,
                    'nombre' => $datosNormalizados['nombre'],
                    'descripcion' => $datosNormalizados['descripcion'],
                    'marca' => $datosNormalizados['marca'],
                    'modelo' => $datosNormalizados['modelo'],
                    'unidad_medida' => $datosNormalizados['unidad_medida'],
                    'costo_referencia' => $costoReferencia,
                    'precio_base' => $precioBase,
                    'activo' => true,
                    'creado_por_id' => $actor->id,
                ]);

                // ---------------------------------------------
                // AUDITORÍA
                // Conserva los datos iniciales del producto,
                // actor, petición, IP, sesión y fecha.
                // ---------------------------------------------

                $this->registrarAuditoria->registrar(
                    accion: 'producto.creado',
                    modulo: 'productos',
                    descripcion: 'Se creó un producto.',
                    actor: $actor,
                    modelo: $producto,
                    valoresNuevos: [
                        'empresa_id' => $empresaBloqueada->id,
                        'producto_id' => $producto->id,
                        'categoria_producto_id' => $categoriaBloqueada->id,
                        'proveedor_principal_id' => $proveedorBloqueado?->id,
                        'sku' => $producto->sku,
                        'codigo_barras' => $producto->codigo_barras,
                        'nombre' => $producto->nombre,
                        'descripcion' => $producto->descripcion,
                        'marca' => $producto->marca,
                        'modelo' => $producto->modelo,
                        'unidad_medida' => $producto->unidad_medida,
                        'costo_referencia' => $producto->costo_referencia,
                        'precio_base' => $producto->precio_base,
                        'activo' => $producto->activo,
                    ],
                    metadatos: [
                        'empresa_nombre' => $empresaBloqueada->nombre,
                        'categoria_nombre' => $categoriaBloqueada->nombre,
                        'proveedor_nombre' => $proveedorBloqueado?->nombre,
                    ],
                    request: $request
                );

                // ---------------------------------------------
                // RESULTADO
                // Devuelve el producto con sus relaciones
                // principales listas para utilizarse.
                // ---------------------------------------------

                return $producto
                    ->fresh()
                    ->load([
                        'empresa',
                        'categoria',
                        'proveedorPrincipal',
                        'creadoPor',
                    ]);
            }
        );
    }

    // ---------------------------------------------------------
    // NORMALIZACIÓN DE DATOS
    // Limpia espacios y unifica identificadores antes de
    // realizar validaciones o guardar información.
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
                    ? Str::lower(
                        trim($unidadMedida)
                    )
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
    // Convierte cadenas vacías en null.
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
    // NORMALIZACIÓN DECIMAL
    // Conserva null para campos vacíos y limpia las cadenas
    // antes de validarlas como valores numéricos.
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
    // CONVERSIÓN DECIMAL
    // Convierte valores válidos a float con dos decimales.
    // ---------------------------------------------------------

    private function decimalOpcional(
        mixed $valor
    ): ?float {
        if ($valor === null) {
            return null;
        }

        return round(
            (float) $valor,
            2
        );
    }

    // ---------------------------------------------------------
    // VALIDACIONES DE NEGOCIO
    // Verifica permisos, relaciones, estados y datos del
    // catálogo antes y durante la transacción.
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validar(
        Empresa $empresa,
        CategoriaProducto $categoria,
        ?Proveedor $proveedor,
        User $actor,
        array $datos
    ): void {
        // ---------------------------------------------
        // PERMISO
        // ---------------------------------------------

        if (! $actor->can('productos.crear')) {
            throw new AuthorizationException(
                'No tienes permiso para crear productos.'
            );
        }

        // ---------------------------------------------
        // EMPRESA
        // ---------------------------------------------

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se pueden crear productos para una empresa inactiva.',
            ]);
        }

        // ---------------------------------------------
        // CATEGORÍA
        // Debe pertenecer a la empresa y estar activa.
        // ---------------------------------------------

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

        // ---------------------------------------------
        // PROVEEDOR PRINCIPAL
        // Es opcional, pero si existe debe pertenecer a
        // la empresa y encontrarse activo.
        // ---------------------------------------------

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

        // ---------------------------------------------
        // SKU
        // ---------------------------------------------

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

        // ---------------------------------------------
        // CÓDIGO DE BARRAS
        // ---------------------------------------------

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

        // ---------------------------------------------
        // NOMBRE
        // ---------------------------------------------

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

        // ---------------------------------------------
        // CAMPOS DE TEXTO OPCIONALES
        // ---------------------------------------------

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

        // ---------------------------------------------
        // UNIDAD DE MEDIDA
        // ---------------------------------------------

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

        // ---------------------------------------------
        // PRECIOS DE REFERENCIA
        // ---------------------------------------------

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
    // VALIDACIÓN DE TEXTO OPCIONAL
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
    // VALIDACIÓN DE DECIMAL OPCIONAL
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
