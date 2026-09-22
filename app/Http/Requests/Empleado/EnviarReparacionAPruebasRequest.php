<?php

namespace App\Http\Requests\Empleado;

use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class EnviarReparacionAPruebasRequest extends FormRequest
{
    public function authorize(): bool
    {
        $orden = $this->route('orden');

        if (! $orden instanceof OrdenServicio) {
            return false;
        }

        return Gate::allows(
            'sendRepairToTesting',
            $orden
        );
    }

    protected function prepareForValidation(): void
    {
        $comentario = $this->input('comentario');

        $this->merge([
            'comentario' => is_string($comentario)
                ? trim($comentario)
                : $comentario,
        ]);
    }

    public function rules(): array
    {
        return [
            'comentario' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'comentario.string' => 'El comentario debe ser un texto válido.',
            'comentario.max' => 'El comentario no puede superar los 2000 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'comentario' => 'comentario de pruebas',
        ];
    }
}
