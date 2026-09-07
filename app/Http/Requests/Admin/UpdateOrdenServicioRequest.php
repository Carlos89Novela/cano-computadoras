<?php

namespace App\Http\Requests\Admin;

use App\Enums\EstadoOrden;
use App\Models\OrdenServicio;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrdenServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => [
                'required',
                Rule::enum(EstadoOrden::class),
                $this->validarTransicionEstado(),
            ],
            'diagnostico' => [
                'nullable',
                'string',
                'max:3000',
            ],
            'costo_estimado' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'costo_final' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
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
            'estado.required' => 'Debes seleccionar un estado.',
            'diagnostico.max' => 'El diagnóstico no puede superar los 3000 caracteres.',
            'costo_estimado.numeric' => 'El costo estimado debe ser un número válido.',
            'costo_estimado.min' => 'El costo estimado no puede ser negativo.',
            'costo_estimado.max' => 'El costo estimado supera el importe permitido.',
            'costo_final.numeric' => 'El costo final debe ser un número válido.',
            'costo_final.min' => 'El costo final no puede ser negativo.',
            'costo_final.max' => 'El costo final supera el importe permitido.',
            'comentario.max' => 'El comentario no puede superar los 2000 caracteres.',
        ];
    }

    private function validarTransicionEstado(): Closure
    {
        return function (
            string $attribute,
            mixed $value,
            Closure $fail
        ): void {
            $orden = $this->route('orden');

            if (! $orden instanceof OrdenServicio) {
                $fail('No fue posible identificar la orden de servicio.');

                return;
            }

            $estadoActual = EstadoOrden::tryFrom($orden->estado);
            $nuevoEstado = EstadoOrden::tryFrom((string) $value);

            if ($estadoActual === null || $nuevoEstado === null) {
                $fail('El estado seleccionado no es válido.');

                return;
            }

            if ($estadoActual === $nuevoEstado) {
                return;
            }

            if (! $estadoActual->permiteTransicionA($nuevoEstado)) {
                $fail(
                    'No se permite cambiar de '
                    .$estadoActual->value
                    .' a '
                    .$nuevoEstado->value
                    .'.'
                );
            }
        };
    }
}
