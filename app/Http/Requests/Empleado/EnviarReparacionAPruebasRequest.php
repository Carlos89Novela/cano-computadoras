<?php

namespace App\Http\Requests\Empleado;

use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Solicitud de Validación para el Envío de una Reparación a Pruebas de Calidad.
 *
 * Aplica los controles de autorización para que el técnico asignado avance la orden a control de calidad:
 * - Valida mediante la Policy `sendRepairToTesting` que el usuario sea el técnico asignado activo
 *   y que la orden se encuentre en estado `EN_REPARACION`.
 * - Normaliza y valida notas o comentarios técnicos sobre las pruebas a realizar.
 */
class EnviarReparacionAPruebasRequest extends FormRequest
{
    /**
     * Determina si el técnico autenticado está facultado para enviar la reparación a pruebas.
     *
     * @return bool Verdadero si la Policy autoriza la transición.
     */
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
     * Define las reglas de validación para el comentario técnico de pruebas.
     *
     * @return array<string, mixed> Reglas de validación aplicables.
     */
    public function rules(): array
    {
        return [
            // Observaciones de control de calidad o pruebas de estabilidad realizadas (opcional)
            'comentario' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados para la validación.
     *
     * @return array<string, string> Mensajes legibles.
     */
    public function messages(): array
    {
        return [
            'comentario.string' => 'El comentario debe ser un texto válido.',
            'comentario.max' => 'El comentario no puede superar los 2000 caracteres.',
        ];
    }

    /**
     * Nombres amigables para los atributos en caso de error.
     *
     * @return array<string, string> Nombres legibles.
     */
    public function attributes(): array
    {
        return [
            'comentario' => 'comentario de pruebas',
        ];
    }
}
