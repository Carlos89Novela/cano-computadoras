<?php

namespace App\Http\Requests\Admin;

use App\Enums\EstadoOrden;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateOrdenServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => [
                'required',
                'array',
                'min:1',
            ],
            'ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:orden_servicios,id',
            ],
            'estado' => [
                'required',
                Rule::enum(EstadoOrden::class),
            ],
            'comentario' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'mensaje_cliente' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Debes seleccionar al menos una orden.',
            'ids.array' => 'La selección de órdenes no es válida.',
            'ids.min' => 'Debes seleccionar al menos una orden.',
            'ids.*.required' => 'Se encontró una orden sin identificador.',
            'ids.*.integer' => 'Uno de los identificadores no es válido.',
            'ids.*.distinct' => 'No se permite seleccionar la misma orden más de una vez.',
            'ids.*.exists' => 'Una de las órdenes seleccionadas ya no existe.',
            'estado.required' => 'Debes seleccionar un estado.',
            'comentario.max' => 'El comentario no puede superar los 2000 caracteres.',
            'mensaje_cliente.max' => 'El mensaje para el cliente no puede superar los 2000 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'órdenes seleccionadas',
            'ids.*' => 'orden seleccionada',
            'estado' => 'estado',
            'comentario' => 'comentario',
            'mensaje_cliente' => 'mensaje para el cliente',
        ];
    }
}
