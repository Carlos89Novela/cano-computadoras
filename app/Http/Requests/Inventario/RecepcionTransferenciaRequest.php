<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;

class RecepcionTransferenciaRequest extends FormRequest
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
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'lineas' => ['nullable', 'array'],
            'lineas.*.detalle_id' => ['required_with:lineas', 'integer', 'exists:transferencia_inventario_detalles,id'],
            'lineas.*.cantidad_recibida' => ['required_with:lineas', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lineas.*.cantidad_recibida.min' => 'La cantidad recibida no puede ser negativa.',
        ];
    }
}
