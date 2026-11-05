<?php

namespace App\Http\Requests\Admin;

use App\Enums\EstadoOrden;
use App\Models\OrdenServicio;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de Validación para la Actualización de una Orden de Servicio por Administrador.
 *
 * Aplica validaciones de negocio críticas, destacando la máquina de estados de taller:
 * - Validador de Transición de Estados: Implementa una Closure de validación que consulta
 *   el método `permiteTransicionA()` en el enum `EstadoOrden`, impidiendo transiciones
 *   ilegales (por ejemplo, de una orden ya 'Entregada' a 'Recibido').
 * - Valida importes monetarios (costo estimado y costo final).
 * - Admite diagnóstico técnico ampliado, comentarios internos y mensajes para el cliente.
 */
class UpdateOrdenServicioRequest extends FormRequest
{
    /**
     * Determina si el usuario tiene autorización para actualizar la orden.
     *
     * @return bool Verdadero si está autorizado por el middleware de administración.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define las reglas de validación para los campos técnicos y de estado de la orden.
     *
     * @return array<string, mixed> Reglas de validación aplicables.
     */
    public function rules(): array
    {
        return [
            // Estado operativo validado contra el enum y la máquina de estados
            'estado' => [
                'required',
                Rule::enum(EstadoOrden::class),
                $this->validarTransicionEstado(),
            ],
            // Diagnóstico técnico de la falla o requerimiento
            'diagnostico' => [
                'nullable',
                'string',
                'max:3000',
            ],
            // Costo estimado propuesto para aprobación del cliente
            'costo_estimado' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            // Costo final definitivo fijado al concluir los trabajos
            'costo_final' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            // Bitácora interna de taller (opcional)
            'comentario' => [
                'nullable',
                'string',
                'max:2000',
            ],
            // Mensaje o notificación visible para el cliente (opcional)
            'mensaje_cliente' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados para la actualización de la orden.
     *
     * @return array<string, string> Mensajes legibles.
     */
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
            'mensaje_cliente.max' => 'El mensaje para el cliente no puede superar los 2000 caracteres.',
        ];
    }

    /**
     * Genera una regla de validación basada en Closure que aplica la máquina de estados.
     *
     * Comprueba si el estado actual de la orden permite avanzar o retroceder hacia
     * el nuevo estado solicitado según el grafo de transiciones definido en EstadoOrden.
     *
     * @return Closure Regla de validación invocable.
     */
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

            // Si el estado no cambió, la transición es una no-operación permitida
            if ($estadoActual === $nuevoEstado) {
                return;
            }

            // Valida contra la matriz de transiciones permitidas del enum
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
