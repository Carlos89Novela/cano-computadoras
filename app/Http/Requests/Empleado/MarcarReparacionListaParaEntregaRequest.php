<?php

namespace App\Http\Requests\Empleado;

use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Solicitud de Validación para Marcar una Reparación como Lista para Entrega.
 *
 * Aplica los controles de autorización y validación monetaria para el cierre técnico:
 * - Valida mediante la Policy `markRepairReadyForDelivery` que el técnico tenga la orden asignada
 *   y que la orden se encuentre en reparación o pruebas habiendo sido autorizada.
 * - Exige el registro obligatorio del costo final definitivo con hasta dos decimales.
 * - Admite notas técnicas de conclusión o entrega para la bitácora interna.
 */
class MarcarReparacionListaParaEntregaRequest extends FormRequest
{
    /**
     * Determina si el empleado autenticado tiene autorización para marcar la orden lista.
     *
     * @return bool Verdadero si la Policy autoriza la finalización técnica.
     */
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

    /**
     * Normaliza los textos de entrada antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $comentario = $this->input('comentario');

        $this->merge([
            'comentario' => is_string($comentario)
                ? trim($comentario)
                : $comentario,
        ]);
    }

    /**
     * Define las reglas de validación para el costo final y las notas de cierre.
     *
     * @return array<string, mixed> Reglas de validación aplicables.
     */
    public function rules(): array
    {
        return [
            // Costo final definitivo de la reparación realizada
            'costo_final' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
            ],
            // Comentarios o notas de cierre técnico de taller (opcional)
            'comentario' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados para el cierre técnico.
     *
     * @return array<string, string> Mensajes legibles.
     */
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

    /**
     * Nombres amigables para los campos de cierre técnico.
     *
     * @return array<string, string> Nombres legibles.
     */
    public function attributes(): array
    {
        return [
            'costo_final' => 'costo final',
            'comentario' => 'comentario de cierre técnico',
        ];
    }
}

