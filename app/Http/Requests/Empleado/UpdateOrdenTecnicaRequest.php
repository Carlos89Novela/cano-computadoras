<?php

namespace App\Http\Requests\Empleado;

use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateOrdenTecnicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $orden = $this->route('orden');

        if (! $orden instanceof OrdenServicio) {
            return false;
        }

        return Gate::allows(
            'updateTechnical',
            $orden
        );
    }

    protected function prepareForValidation(): void
    {
        $diagnostico = $this->input('diagnostico');
        $comentario = $this->input('comentario');

        $this->merge([
            'diagnostico' => is_string($diagnostico)
                ? trim($diagnostico)
                : $diagnostico,
            'comentario' => is_string($comentario)
                ? trim($comentario)
                : $comentario,
        ]);
    }

    public function rules(): array
    {
        return [
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
            'comentario' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'estado' => [
                'prohibited',
            ],
            'costo_final' => [
                'prohibited',
            ],
            'mensaje_cliente' => [
                'prohibited',
            ],
            'empleado_id' => [
                'prohibited',
            ],
            'autorizacion' => [
                'prohibited',
            ],
            'fecha_entrega' => [
                'prohibited',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'diagnostico.string' => 'El diagnóstico debe ser un texto válido.',
            'diagnostico.max' => 'El diagnóstico no puede superar los 3000 caracteres.',

            'costo_estimado.numeric' => 'El costo estimado debe ser un número válido.',
            'costo_estimado.min' => 'El costo estimado no puede ser negativo.',
            'costo_estimado.max' => 'El costo estimado supera el importe permitido.',

            'comentario.string' => 'El comentario interno debe ser un texto válido.',
            'comentario.max' => 'El comentario interno no puede superar los 2000 caracteres.',

            'estado.prohibited' => 'No tienes permiso para modificar directamente el estado.',
            'costo_final.prohibited' => 'No tienes permiso para modificar el costo final.',
            'mensaje_cliente.prohibited' => 'No tienes permiso para enviar mensajes al cliente desde esta sección.',
            'empleado_id.prohibited' => 'No tienes permiso para cambiar la asignación.',
            'autorizacion.prohibited' => 'No tienes permiso para autorizar el presupuesto.',
            'fecha_entrega.prohibited' => 'No tienes permiso para modificar la fecha de entrega.',
        ];
    }

    public function attributes(): array
    {
        return [
            'diagnostico' => 'diagnóstico',
            'costo_estimado' => 'costo estimado',
            'comentario' => 'comentario interno',
            'estado' => 'estado',
            'costo_final' => 'costo final',
            'mensaje_cliente' => 'mensaje para el cliente',
            'empleado_id' => 'empleado asignado',
            'autorizacion' => 'autorización',
            'fecha_entrega' => 'fecha de entrega',
        ];
    }
}
