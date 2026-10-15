<?php

namespace App\Http\Requests\Inventario;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarEntradaInventarioRequest extends FormRequest
{
    // ---------------------------------------------------------
    // AUTORIZACIÓN
    // Requiere una empresa válida y el permiso específico para
    // registrar entradas de inventario.
    // ---------------------------------------------------------

    public function authorize(): bool
    {
        $actor = $this->user();
        $empresa = $this->route('empresa');

        return $actor instanceof User
            && $empresa instanceof Empresa
            && $actor->can(
                'inventario.registrar_entrada'
            );
    }

    // ---------------------------------------------------------
    // NORMALIZACIÓN
    // Recorta los valores antes de aplicar las reglas para que
    // las cadenas formadas únicamente por espacios sean
    // rechazadas correctamente.
    // ---------------------------------------------------------

    protected function prepareForValidation(): void
    {
        $campos = [
            'cantidad',
            'costo_unitario',
            'motivo',
        ];

        $datosNormalizados = [];

        foreach ($campos as $campo) {
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
    // REGLAS DE VALIDACIÓN
    // El almacén y el producto deben pertenecer a la empresa
    // recibida mediante la ruta y encontrarse activos.
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

        return [
            // -------------------------------------------------
            // ALMACÉN
            // Debe pertenecer a la empresa, estar activo y
            // permitir el manejo de existencias.
            // -------------------------------------------------

            'almacen_id' => [
                'required',
                'integer',
                Rule::exists(
                    'almacenes',
                    'id'
                )->where(
                    fn ($consulta) => $consulta
                        ->where(
                            'empresa_id',
                            $empresaId
                        )
                        ->where(
                            'activo',
                            true
                        )
                        ->where(
                            'permite_existencias',
                            true
                        )
                ),
            ],

            // -------------------------------------------------
            // PRODUCTO
            // Debe pertenecer a la misma empresa y encontrarse
            // activo para poder recibir unidades.
            // -------------------------------------------------

            'producto_id' => [
                'required',
                'integer',
                Rule::exists(
                    'productos',
                    'id'
                )->where(
                    fn ($consulta) => $consulta
                        ->where(
                            'empresa_id',
                            $empresaId
                        )
                        ->where(
                            'activo',
                            true
                        )
                ),
            ],

            // -------------------------------------------------
            // CANTIDAD
            // Debe ser mayor que cero y admitir como máximo
            // cuatro posiciones decimales.
            // -------------------------------------------------

            'cantidad' => [
                'required',
                'string',
                'regex:/^\d+(?:\.\d{1,4})?$/',
                'gt:0',
            ],

            // -------------------------------------------------
            // COSTO UNITARIO
            // Puede ser cero, pero no negativo. Conserva hasta
            // cuatro posiciones decimales.
            // -------------------------------------------------

            'costo_unitario' => [
                'required',
                'string',
                'regex:/^\d+(?:\.\d{1,4})?$/',
            ],

            // -------------------------------------------------
            // MOTIVO
            // Documenta por qué se realizó la entrada.
            // -------------------------------------------------

            'motivo' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }

    // ---------------------------------------------------------
    // MENSAJES
    // Proporciona mensajes claros para el formulario de entrada
    // de inventario.
    // ---------------------------------------------------------

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'almacen_id.required' => 'Debes seleccionar un almacén.',

            'almacen_id.integer' => 'El almacén seleccionado no es válido.',

            'almacen_id.exists' => 'El almacén seleccionado no está disponible para recibir existencias.',

            'producto_id.required' => 'Debes seleccionar un producto.',

            'producto_id.integer' => 'El producto seleccionado no es válido.',

            'producto_id.exists' => 'El producto seleccionado no está disponible para esta empresa.',

            'cantidad.required' => 'Debes indicar la cantidad de entrada.',

            'cantidad.string' => 'La cantidad de entrada no es válida.',

            'cantidad.regex' => 'La cantidad debe ser mayor que cero y tener hasta cuatro decimales.',

            'cantidad.gt' => 'La cantidad de entrada debe ser mayor que cero.',

            'costo_unitario.required' => 'Debes indicar el costo unitario.',

            'costo_unitario.string' => 'El costo unitario no es válido.',

            'costo_unitario.regex' => 'El costo unitario debe ser igual o mayor que cero y tener hasta cuatro decimales.',

            'motivo.required' => 'Debes indicar el motivo de la entrada.',

            'motivo.string' => 'El motivo de la entrada no es válido.',

            'motivo.max' => 'El motivo no puede superar los 1000 caracteres.',
        ];
    }

    // ---------------------------------------------------------
    // ATRIBUTOS
    // Define nombres legibles para posibles mensajes generados
    // automáticamente por Laravel.
    // ---------------------------------------------------------

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'almacen_id' => 'almacén',
            'producto_id' => 'producto',
            'cantidad' => 'cantidad',
            'costo_unitario' => 'costo unitario',
            'motivo' => 'motivo',
        ];
    }
}
