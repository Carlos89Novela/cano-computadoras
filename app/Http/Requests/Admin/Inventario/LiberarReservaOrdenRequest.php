<?php

namespace App\Http\Requests\Admin\Inventario;

use App\Models\Empresa;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LiberarReservaOrdenRequest extends FormRequest
{
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

    protected function prepareForValidation(): void
    {
        $cantidad = $this->input('cantidad');
        $motivo = $this->input('motivo');

        $this->merge([
            'cantidad' => is_string($cantidad) ? trim($cantidad) : $cantidad,
            'motivo' => is_string($motivo) ? trim($motivo) : $motivo,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $empresa = $this->route('empresa');
        $empresaId = $empresa instanceof Empresa ? $empresa->id : 0;

        return [
            'almacen_id' => [
                'required',
                'integer',
                Rule::exists('almacenes', 'id')->where(
                    fn ($consulta) => $consulta->where('empresa_id', $empresaId)
                ),
            ],
            'producto_id' => [
                'required',
                'integer',
                Rule::exists('productos', 'id')->where(
                    fn ($consulta) => $consulta->where('empresa_id', $empresaId)
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'almacen_id.required' => 'Debes indicar el almacén.',
            'almacen_id.exists' => 'El almacén no pertenece a la empresa indicada.',
            'producto_id.required' => 'Debes indicar el producto.',
            'producto_id.exists' => 'El producto no pertenece a la empresa indicada.',
            'cantidad.required' => 'Indica la cantidad a liberar.',
            'cantidad.regex' => 'La cantidad debe ser numérica y tener hasta cuatro decimales.',
            'cantidad.gt' => 'La cantidad a liberar debe ser mayor que cero.',
            'motivo.required' => 'El motivo de la liberación es obligatorio.',
            'motivo.max' => 'El motivo no puede superar los 1000 caracteres.',
        ];
    }
}
