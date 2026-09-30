<?php

namespace App\Http\Requests;

use App\Enums\EstadoAutorizacion;
use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de Validación para la Autorización o Rechazo de Cotizaciones por el Cliente.
 *
 * Aplica los controles de seguridad y validación cuando el cliente decide sobre el presupuesto:
 * - Valida mediante la Policy `authorizeBudget` que el usuario sea el dueño de la orden
 *   y que la orden se encuentre en espera de autorización y con cotización aprobada por supervisor.
 * - Restringe la decisión a los valores admitidos por el enum `EstadoAutorizacion::decisiones()`.
 */
class AutorizarOrdenServicioRequest extends FormRequest
{
    /**
     * Determina si el cliente autenticado está facultado para emitir su decisión sobre la orden.
     *
     * @return bool Verdadero si la Policy autoriza la operación.
     */
    public function authorize(): bool
    {
        $orden = $this->route('orden');

        return $orden instanceof OrdenServicio
            && $this->user()?->can(
                'authorizeBudget',
                $orden
            ) === true;
    }

    /**
     * Define las reglas de validación para la decisión del cliente.
     *
     * @return array<string, mixed> Reglas de validación.
     */
    public function rules(): array
    {
        return [
            // La decisión solo puede ser 'autorizada' o 'rechazada'
            'decision' => [
                'required',
                'string',
                Rule::in(
                    EstadoAutorizacion::decisiones()
                ),
            ],
        ];
    }

    /**
     * Mensajes de error personalizados para la validación.
     *
     * @return array<string, string> Mensajes de error específicos.
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Debes seleccionar una decisión.',
            'decision.in' => 'La decisión seleccionada no es válida.',
        ];
    }

    /**
     * Nombres amigables para los campos en mensajes de validación.
     *
     * @return array<string, string> Nombres legibles.
     */
    public function attributes(): array
    {
        return [
            'decision' => 'decisión',
        ];
    }
}
