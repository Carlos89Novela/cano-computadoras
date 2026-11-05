<?php

namespace App\Http\Requests\Empleado;

use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Solicitud de Validación para la Actualización Técnica de Órdenes por el Empleado.
 *
 * Aplica el principio de mínimo privilegio y delimitación estricta de responsabilidades:
 * - Valida mediante la Policy `updateTechnical` que el usuario sea el técnico asignado activo
 *   y que la orden se encuentre en una etapa que admita actualizaciones de diagnóstico.
 * - Campos autorizados: Diagnóstico técnico, costo estimado y comentarios internos de taller.
 * - Campos expresamente prohibidos (`prohibited`): Estado, costo final, mensaje al cliente,
 *   reasignación de técnico, autorización y fecha de entrega. Esto impide cualquier intento
 *   de saltarse los controles de supervisión o las decisiones de aprobación del cliente.
 */
class UpdateOrdenTecnicaRequest extends FormRequest
{
    /**
     * Determina si el empleado autenticado tiene permiso para modificar el expediente técnico.
     *
     * @return bool Verdadero si la Policy autoriza la edición técnica.
     */
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

    /**
     * Normaliza los textos del diagnóstico y comentarios antes de validar.
     */
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

    /**
     * Define las reglas de validación permitidas y los campos estrictamente prohibidos.
     *
     * @return array<string, mixed> Reglas de validación aplicables.
     */
    public function rules(): array
    {
        return [
            // Diagnóstico técnico detallado de la avería
            'diagnostico' => [
                'nullable',
                'string',
                'max:3000',
            ],
            // Presupuesto o costo estimado propuesto para supervisión y cliente
            'costo_estimado' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            // Comentario interno para el historial de bitácora
            'comentario' => [
                'nullable',
                'string',
                'max:2000',
            ],

            // -----------------------------------------------------------------
            // Campos Prohibidos para el Rol Técnico en esta Petición
            // -----------------------------------------------------------------
            // El técnico no puede saltarse la máquina de estados directamente
            'estado' => [
                'prohibited',
            ],
            // El costo final solo puede fijarse al marcar lista para entrega
            'costo_final' => [
                'prohibited',
            ],
            // Los mensajes públicos al cliente corresponden a supervisión / administración
            'mensaje_cliente' => [
                'prohibited',
            ],
            // La asignación de técnicos es facultad de supervisión
            'empleado_id' => [
                'prohibited',
            ],
            // La autorización del presupuesto compete exclusivamente al cliente
            'autorizacion' => [
                'prohibited',
            ],
            // La fecha de entrega solo se establece durante la entrega física
            'fecha_entrega' => [
                'prohibited',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados para la actualización técnica.
     *
     * @return array<string, string> Mensajes legibles.
     */
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

    /**
     * Nombres amigables para los campos evaluados.
     *
     * @return array<string, string> Nombres legibles.
     */
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
