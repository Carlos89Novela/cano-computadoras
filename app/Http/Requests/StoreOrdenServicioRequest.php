<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de Validación para la Creación de Órdenes de Servicio por el Cliente.
 *
 * Aplica reglas de integridad referencial y seguridad multicapa:
 * - Aislamiento estricto de propiedad: Valida que el equipo seleccionado pertenezca
 *   obligatoriamente al usuario autenticado, impidiendo asociar equipos de terceros.
 * - Validación de catálogo de servicios: Si se especifica un servicio preliminar,
 *   verifica que exista y se encuentre marcado como activo en el catálogo.
 * - Calidad de descripción de falla: Exige un mínimo de detalle (10 caracteres)
 *   para garantizar que los técnicos dispongan de contexto suficiente para el diagnóstico.
 */
class StoreOrdenServicioRequest extends FormRequest
{
    /**
     * Determina si el usuario tiene autorización para generar órdenes de reparación.
     *
     * @return bool Verdadero si el usuario está autenticado.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normaliza los textos de entrada antes de aplicar las validaciones.
     */
    protected function prepareForValidation(): void
    {
        $problemaReportado = $this->input('problema_reportado');

        $this->merge([
            'problema_reportado' => is_string($problemaReportado)
                ? trim($problemaReportado)
                : $problemaReportado,
        ]);
    }

    /**
     * Define las reglas de validación para la creación de la orden.
     *
     * @return array<string, mixed> Reglas de validación.
     */
    public function rules(): array
    {
        return [
            // El equipo debe pertenecer obligatoriamente al cliente que realiza la petición
            'equipo_id' => [
                'required',
                'integer',
                Rule::exists('equipos', 'id')->where(
                    'user_id',
                    $this->user()?->id
                ),
            ],
            // Descripción detallada del síntoma o falla reportada por el cliente
            'problema_reportado' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
            // Servicio preliminar solicitado (opcional, debe estar activo si se proporciona)
            'servicio_id' => [
                'nullable',
                'integer',
                Rule::exists('servicios', 'id')->where(
                    'activo',
                    true
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
            'equipo_id.required' => 'Debes seleccionar un equipo.',
            'equipo_id.integer' => 'El equipo seleccionado no es válido.',
            'equipo_id.exists' => 'El equipo seleccionado no existe o no te pertenece.',
            'problema_reportado.required' => 'Debes describir el problema del equipo.',
            'problema_reportado.min' => 'La descripción del problema debe tener al menos 10 caracteres.',
            'problema_reportado.max' => 'La descripción del problema no puede superar los 2000 caracteres.',
            'servicio_id.integer' => 'El servicio seleccionado no es válido.',
            'servicio_id.exists' => 'El servicio seleccionado no está disponible.',
        ];
    }

    /**
     * Nombres legibles para los atributos en mensajes de error.
     *
     * @return array<string, string> Nombres legibles.
     */
    public function attributes(): array
    {
        return [
            'equipo_id' => 'equipo',
            'problema_reportado' => 'problema reportado',
            'servicio_id' => 'servicio',
        ];
    }
}
