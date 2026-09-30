<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitud de Validación para la Asignación y Reasignación de Órdenes de Servicio.
 *
 * Aplica los controles de autorización y reglas de integridad para vincular
 * un empleado técnico responsable a una orden de servicio de taller:
 * - Valida permisos operativos de asignación o reasignación vía Spatie.
 * - Limpia y normaliza las observaciones o instrucciones para el técnico.
 * - Verifica la existencia del identificador del empleado en la tabla de usuarios.
 */
class AsignarOrdenRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado tiene autorización para asignar técnicos a órdenes.
     *
     * @return bool Verdadero si el usuario cuenta con los permisos requeridos.
     */
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario !== null
            && $usuario->hasAnyPermission([
                'ordenes.asignar',
                'ordenes.reasignar',
            ]);
    }

    /**
     * Normaliza los datos antes de aplicar las reglas de validación.
     */
    protected function prepareForValidation(): void
    {
        $observaciones = $this->input('observaciones');

        $this->merge([
            'observaciones' => is_string($observaciones)
                ? trim($observaciones)
                : $observaciones,
        ]);
    }

    /**
     * Define las reglas de validación aplicables a la petición.
     *
     * @return array<string, mixed> Matriz de reglas de validación.
     */
    public function rules(): array
    {
        return [
            // El técnico debe existir obligatoriamente en la tabla de usuarios
            'empleado_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            // Notas u orientaciones de trabajo opcionales para el técnico asignado
            'observaciones' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados para la validación.
     *
     * @return array<string, string> Matriz asociativa de mensajes.
     */
    public function messages(): array
    {
        return [
            'empleado_id.required' => 'Debes seleccionar un empleado.',
            'empleado_id.integer' => 'El empleado seleccionado no es válido.',
            'empleado_id.exists' => 'El empleado seleccionado no existe.',
            'observaciones.max' => 'Las observaciones no pueden superar los 2000 caracteres.',
        ];
    }

    /**
     * Nombres legibles para los atributos en caso de fallos de validación.
     *
     * @return array<string, string> Nombres de atributos amigables.
     */
    public function attributes(): array
    {
        return [
            'empleado_id' => 'empleado',
            'observaciones' => 'observaciones',
        ];
    }
}
