<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;

class TransferenciaInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('inventario.transferir') || $user->hasRole('administrador'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'almacen_origen_id' => ['required', 'integer', 'exists:almacenes,id'],
            'almacen_destino_id' => ['required', 'integer', 'exists:almacenes,id', 'different:almacen_origen_id'],
            'motivo' => ['required', 'string', 'max:1000'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.producto_id' => ['required', 'integer', 'exists:productos,id', 'distinct'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:99999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'almacen_destino_id.different' => 'El almacén de destino no puede ser igual al de origen.',
            'motivo.required' => 'Debes registrar un motivo para la transferencia.',
            'lineas.required' => 'Debes transferir al menos un artículo.',
            'lineas.*.producto_id.distinct' => 'No puedes transferir el mismo producto más de una vez en el documento.',
            'lineas.*.cantidad.gt' => 'La cantidad a transferir debe ser mayor a cero.',
        ];
    }
}
