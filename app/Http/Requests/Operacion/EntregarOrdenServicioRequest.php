<?php

namespace App\Http\Requests\Operacion;

use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
        $accion = $this->input('accion_comprobante', 'imprimir');

        $this->merge([
            'comentario' => is_string($comentario) ? trim($comentario) : $comentario,
            'accion_comprobante' => is_string($accion) ? trim($accion) : 'imprimir',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'comentario' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'accion_comprobante' => [
                'required',
                'string',
                Rule::in(['ninguna', 'imprimir', 'correo', 'ambas']),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comentario.string' => 'El comentario de entrega debe ser un texto válido.',
            'comentario.max' => 'El comentario de entrega no puede superar los 2000 caracteres.',
            'accion_comprobante.in' => 'La opción de comprobante seleccionada no es válida.',
        ];
    }
}
