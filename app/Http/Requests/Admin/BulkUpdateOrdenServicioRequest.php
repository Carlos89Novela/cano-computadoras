<?php

namespace App\Http\Requests\Admin;

use App\Enums\EstadoOrden;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de Validación para la Actualización Masiva de Órdenes de Servicio.
 *
 * Valida operaciones por lote sobre múltiples órdenes seleccionadas en la interfaz de administración:
 * - Valida la existencia y no duplicidad de los IDs de órdenes de servicio seleccionados.
 * - Valida que el nuevo estado objetivo corresponda a un caso válido del enum `EstadoOrden`.
 * - Admite comentarios internos de taller y notas públicas destinadas al cliente.
 */
class BulkUpdateOrdenServicioRequest extends FormRequest
{
    /**
     * Determina si el usuario está facultado para ejecutar actualizaciones masivas.
     *
     * @return bool Verdadero si está autorizado por middleware de administración.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define las reglas de validación para la operación en lote.
     *
     * @return array<string, mixed> Reglas de validación aplicables.
     */
    public function rules(): array
    {
        return [
            // Arreglo de identificadores de órdenes seleccionadas en la tabla
            'ids' => [
                'required',
                'array',
                'min:1',
            ],
            // Cada identificador debe ser único en la lista y existir en la base de datos
            'ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:orden_servicios,id',
            ],
            // Estado operativo al que se desea transicionar las órdenes seleccionadas
            'estado' => [
                'required',
                Rule::enum(EstadoOrden::class),
            ],
            // Comentario interno para la bitácora técnica de taller (opcional)
            'comentario' => [
                'nullable',
                'string',
                'max:2000',
            ],
            // Notificación opcional que será visible para el cliente
            'mensaje_cliente' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados para la validación por lote.
     *
     * @return array<string, string> Mensajes legibles.
     */
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

    /**
     * Nombres amigables para los campos de validación masiva.
     *
     * @return array<string, string> Nombres legibles.
     */
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
