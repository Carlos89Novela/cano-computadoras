<?php

namespace App\Http\Requests\Operacion;

use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class EntregarOrdenServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $orden = $this->route('orden');

        if (! $orden instanceof OrdenServicio) {
            return false;
        }

        return Gate::allows(
            'deliver',
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
            'comentario.string' => 'El comentario de entrega debe ser un texto válido.',
            'comentario.max' => 'El comentario de entrega no puede superar los 2000 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'comentario' => 'comentario de entrega',
        ];
    }
}
