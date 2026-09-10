<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AsignarOrdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario !== null
            && $usuario->hasAnyPermission([
                'ordenes.asignar',
                'ordenes.reasignar',
            ]);
    }

    protected function prepareForValidation(): void
    {
        $observaciones = $this->input('observaciones');

        $this->merge([
            'observaciones' => is_string($observaciones)
                ? trim($observaciones)
                : $observaciones,
        ]);
    }

    public function rules(): array
    {
        return [
            'empleado_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'observaciones' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'empleado_id.required' => 'Debes seleccionar un empleado.',
            'empleado_id.integer' => 'El empleado seleccionado no es válido.',
            'empleado_id.exists' => 'El empleado seleccionado no existe.',
            'observaciones.max' => 'Las observaciones no pueden superar los 2000 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'empleado_id' => 'empleado',
            'observaciones' => 'observaciones',
        ];
    }
}
