<?php

namespace App\Http\Requests\Productos;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearProductoRequest extends FormRequest
{
    // ---------------------------------------------------------
    // AUTORIZACIÓN
    // Permite crear productos únicamente dentro de una empresa
    // válida y con el permiso productos.crear.
    // ---------------------------------------------------------

    public function authorize(): bool
    {
        $actor = $this->user();
        $empresa = $this->route('empresa');

        return $actor instanceof User
            && $empresa instanceof Empresa
            && $actor->can('productos.crear');
    }

    // ---------------------------------------------------------
    // NORMALIZACIÓN
    // Limpia los textos y unifica el SKU y la unidad de medida
    // antes de ejecutar las reglas de validación.
    // ---------------------------------------------------------

    protected function prepareForValidation(): void
    {
        $camposOpcionales = [
            'codigo_barras',
            'descripcion',
            'marca',
            'modelo',
            'costo_referencia',
            'precio_base',
        ];

        $datosNormalizados = [
            'sku' => is_string($this->input('sku'))
                ? mb_strtoupper(trim($this->input('sku')))
                : $this->input('sku'),

            'nombre' => is_string($this->input('nombre'))
                ? trim($this->input('nombre'))
                : $this->input('nombre'),

            'unidad_medida' => is_string($this->input('unidad_medida'))
                    ? mb_strtolower(
                        trim($this->input('unidad_medida'))
                    )
                    : $this->input('unidad_medida'),
        ];

        foreach ($camposOpcionales as $campo) {
            $valor = $this->input($campo);

            $datosNormalizados[$campo] = is_string($valor)
                ? trim($valor)
                : $valor;
        }

        $this->merge(
            $datosNormalizados
        );
    }

    // ---------------------------------------------------------
    // REGLAS
    // Valida los datos generales y limita categoría y proveedor
    // al ámbito de la empresa recibida mediante la ruta.
    // ---------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $empresa = $this->route('empresa');

        $empresaId = $empresa instanceof Empresa
            ? $empresa->id
            : 0;

        $unidadesMedida = config(
            'access_control.unidades_medida_productos',
            []
        );

        return [
            'categoria_producto_id' => [
                'required',
                'integer',
                Rule::exists(
                    'categorias_producto',
                    'id'
                )->where(
                    fn ($consulta) => $consulta
                        ->where(
                            'empresa_id',
                            $empresaId
                        )
                        ->where('activo', true)
                ),
            ],

            'proveedor_principal_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'proveedores',
                    'id'
                )->where(
                    fn ($consulta) => $consulta
                        ->where(
                            'empresa_id',
                            $empresaId
                        )
                        ->where('activo', true)
                ),
            ],

            'sku' => [
                'required',
                'string',
                'max:80',
                'regex:/^[A-Z0-9._-]+$/',
            ],

            'codigo_barras' => [
                'nullable',
                'string',
                'max:100',
            ],

            'nombre' => [
                'required',
                'string',
                'max:200',
            ],

            'descripcion' => [
                'nullable',
                'string',
                'max:4000',
            ],

            'marca' => [
                'nullable',
                'string',
                'max:120',
            ],

            'modelo' => [
                'nullable',
                'string',
                'max:120',
            ],

            'unidad_medida' => [
                'required',
                'string',
                Rule::in(
                    is_array($unidadesMedida)
                        ? $unidadesMedida
                        : []
                ),
            ],

            'costo_referencia' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],

            'precio_base' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
        ];
    }

    // ---------------------------------------------------------
    // MENSAJES
    // Proporciona mensajes claros para el formulario.
    // ---------------------------------------------------------

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categoria_producto_id.required' => 'Debes seleccionar una categoría.',
            'categoria_producto_id.exists' => 'La categoría seleccionada no está disponible.',

            'proveedor_principal_id.exists' => 'El proveedor seleccionado no está disponible.',

            'sku.required' => 'El SKU es obligatorio.',
            'sku.max' => 'El SKU no puede superar 80 caracteres.',
            'sku.regex' => 'El SKU solo puede contener letras, números, puntos, guiones y guiones bajos.',

            'codigo_barras.max' => 'El código de barras no puede superar 100 caracteres.',

            'nombre.required' => 'El nombre del producto es obligatorio.',
            'nombre.max' => 'El nombre no puede superar 200 caracteres.',

            'descripcion.max' => 'La descripción no puede superar 4000 caracteres.',
            'marca.max' => 'La marca no puede superar 120 caracteres.',
            'modelo.max' => 'El modelo no puede superar 120 caracteres.',

            'unidad_medida.required' => 'Debes seleccionar una unidad de medida.',
            'unidad_medida.in' => 'La unidad de medida seleccionada no es válida.',

            'costo_referencia.numeric' => 'El costo de referencia debe ser numérico.',
            'costo_referencia.min' => 'El costo de referencia no puede ser negativo.',
            'costo_referencia.max' => 'El costo de referencia supera el importe permitido.',

            'precio_base.numeric' => 'El precio base debe ser numérico.',
            'precio_base.min' => 'El precio base no puede ser negativo.',
            'precio_base.max' => 'El precio base supera el importe permitido.',
        ];
    }
}
