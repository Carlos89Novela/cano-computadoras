<?php

namespace App\Http\Requests\Empleado;

use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class MarcarReparacionListaParaEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $orden = $this->route('orden');

        if (! $orden instanceof OrdenServicio) {
            return false;
        }

        return Gate::allows(
            'markRepairReadyForDelivery',
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
            'costo_final' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
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
            'costo_final.required' => 'Debes registrar el costo final.',
            'costo_final.numeric' => 'El costo final debe ser un número válido.',
            'costo_final.min' => 'El costo final no puede ser negativo.',
            'costo_final.max' => 'El costo final supera el importe permitido.',
            'costo_final.decimal' => 'El costo final puede tener hasta dos decimales.',
            'comentario.string' => 'El comentario debe ser un texto válido.',
            'comentario.max' => 'El comentario no puede superar los 2000 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'costo_final' => 'costo final',
            'comentario' => 'comentario de cierre técnico',
        ];
    }
}
