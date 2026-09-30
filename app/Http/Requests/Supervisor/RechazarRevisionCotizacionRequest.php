<?php

namespace App\Http\Requests\Supervisor;

use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Solicitud de Validación para el Rechazo de Cotizaciones por Supervisión.
 *
 * Aplica los controles de autorización y fundamentación obligatoria para devolver presupuestos:
 * - Valida mediante la Policy `rejectQuoteReview` que el usuario posea el permiso
 *   `ordenes.rechazar_cotizacion` y que la orden se encuentre en revisión `PENDIENTE`.
 * - Exige el registro obligatorio de observaciones de rechazo para orientar al técnico
 *   sobre las correcciones que debe realizar al diagnóstico o presupuesto propuesto.
 */
class RechazarRevisionCotizacionRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado tiene facultades para rechazar cotizaciones.
     *
     * @return bool Verdadero si la Policy autoriza el rechazo.
     */
    public function authorize(): bool
    {
        $orden = $this->route('orden');

        if (! $orden instanceof OrdenServicio) {
            return false;
        }

        return Gate::allows(
            'rejectQuoteReview',
            $orden
        );
    }

    /**
     * Normaliza los textos de la observación antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $observacion = $this->input('observacion');

        $this->merge([
            'observacion' => is_string($observacion)
                ? trim($observacion)
                : $observacion,
        ]);
    }

    /**
     * Define las reglas de validación para las observaciones de rechazo.
     *
     * @return array<string, mixed> Reglas de validación aplicables.
     */
    public function rules(): array
    {
        return [
            // Justificación técnica o motivo de corrección exigido al supervisor
            'observacion' => [
                'required',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados para el rechazo de cotización.
     *
     * @return array<string, string> Mensajes legibles.
     */
    public function messages(): array
    {
        return [
            'observacion.required' => 'Debes indicar el motivo del rechazo.',
            'observacion.string' => 'La observación debe ser un texto válido.',
            'observacion.max' => 'La observación no puede superar los 2000 caracteres.',
        ];
    }

    /**
     * Nombres amigables para los campos evaluados.
     *
     * @return array<string, string> Nombres legibles.
     */
    public function attributes(): array
    {
        return [
            'observacion' => 'observación',
        ];
    }
}

