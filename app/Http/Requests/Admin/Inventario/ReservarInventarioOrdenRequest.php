<?php

namespace App\Http\Requests\Admin\Inventario;

use App\Models\Empresa;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReservarInventarioOrdenRequest extends FormRequest
{
    // ---------------------------------------------------------
    // AUTORIZACIÓN
    // Exige un actor autenticado con permiso para administrar
    // consumos y reservas de inventario.
    // ---------------------------------------------------------

    public function authorize(): bool
    {
        $actor = $this->user();
        $empresa = $this->route('empresa');
        $orden = $this->route('orden');

        return $actor instanceof User
            && $empresa instanceof Empresa
            && $orden instanceof OrdenServicio
            && $actor->can('inventario.consumir');
    }

    // ---------------------------------------------------------
    // NORMALIZACIÓN
    // Recorta la cantidad y el motivo antes de aplicar las
    // reglas de validación.
    // ---------------------------------------------------------

    protected function prepareForValidation(): void
    {
        $cantidad = $this->input('cantidad');
        $motivo = $this->input('motivo');

        $this->merge([
            'cantidad' => is_string($cantidad)
                ? trim($cantidad)
                : $cantidad,
            'motivo' => is_string($motivo)
                ? trim($motivo)
                : $motivo,
        ]);
    }

    // ---------------------------------------------------------
    // REGLAS
    // Los almacenes y productos deben pertenecer exactamente
    // a la empresa recibida mediante la ruta.
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
            'almacen_id' => [
                'required',
                'integer',
                Rule::exists(
                    'almacenes',
                    'id'
                )->where(
                    fn ($consulta) => $consulta->where(
                        'empresa_id',
                        $empresaId
                    )
                ),
            ],
            'producto_id' => [
                'required',
                'integer',
                Rule::exists(
                    'productos',
                    'id'
                )->where(
                    fn ($consulta) => $consulta->where(
                        'empresa_id',
                        $empresaId
                    )
                ),
            ],
            'cantidad' => [
                'required',
                'regex:/^\d+(?:\.\d{1,4})?$/',
                'gt:0',
            ],
            'motivo' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }

    // ---------------------------------------------------------
    // MENSAJES
    // Devuelve mensajes claros para el formulario de reserva.
    // ---------------------------------------------------------

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'almacen_id.required' => 'Debes seleccionar un almacén.',
            'almacen_id.integer' => 'El almacén seleccionado no es válido.',
            'almacen_id.exists' => 'El almacén seleccionado no pertenece a la empresa indicada.',

            'producto_id.required' => 'Debes seleccionar un producto.',
            'producto_id.integer' => 'El producto seleccionado no es válido.',
            'producto_id.exists' => 'El producto seleccionado no pertenece a la empresa indicada.',

            'cantidad.required' => 'Debes indicar la cantidad a reservar.',
            'cantidad.regex' => 'La cantidad debe ser numérica y tener hasta cuatro decimales.',
            'cantidad.gt' => 'La cantidad a reservar debe ser mayor que cero.',

            'motivo.required' => 'Debes indicar el motivo de la reserva.',
            'motivo.string' => 'El motivo de la reserva no es válido.',
            'motivo.max' => 'El motivo no puede superar los 1000 caracteres.',
        ];
    }
}
