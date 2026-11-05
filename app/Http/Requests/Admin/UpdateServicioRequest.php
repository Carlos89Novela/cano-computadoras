<?php

namespace App\Http\Requests\Admin;

use App\Models\Servicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de Validación para la Modificación de un Servicio del Catálogo.
 *
 * Valida los cambios sobre tarifas, descripción o disponibilidad de un servicio:
 * - Unicidad condicional: Valida que el nombre no colisione con otros servicios existentes,
 *   ignorando el identificador del propio servicio en edición.
 * - Validación monetaria y coherencia de importes.
 * - Normalización de la bandera booleana de estado activo.
 */
class UpdateServicioRequest extends FormRequest
{
    /**
     * Determina si el usuario tiene autorización para actualizar servicios.
     *
     * @return bool Verdadero si está autorizado por el middleware de administración.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza el valor booleano del campo activo antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'activo' => $this->boolean('activo'),
        ]);
    }

    /**
     * Define las reglas de validación para actualizar el servicio.
     *
     * @return array<string, mixed> Reglas de validación aplicables.
     */
    public function rules(): array
    {
        $servicio = $this->route('servicio');

        return [
            // Nombre del servicio con verificación de unicidad excluyendo el registro actual
            'nombre' => [
                'required',
                'string',
                'max:150',
                Rule::unique('servicios', 'nombre')->ignore(
                    $servicio instanceof Servicio
                        ? $servicio->id
                        : null
                ),
            ],
            // Descripción técnica o alcance del servicio (opcional)
            'descripcion' => [
                'nullable',
                'string',
                'max:2000',
            ],
            // Tarifa o precio monetario asignado al servicio
            'precio' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            // Estado de activación para nuevas recepciones en taller
            'activo' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados para la modificación de servicios.
     *
     * @return array<string, string> Mensajes legibles.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del servicio es obligatorio.',
            'nombre.max' => 'El nombre no puede superar los 150 caracteres.',
            'nombre.unique' => 'Ya existe un servicio con ese nombre.',
            'descripcion.max' => 'La descripción no puede superar los 2000 caracteres.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.numeric' => 'El precio debe ser un número válido.',
            'precio.min' => 'El precio no puede ser negativo.',
            'precio.max' => 'El precio supera el importe permitido.',
            'activo.required' => 'Debes indicar si el servicio está activo.',
            'activo.boolean' => 'El estado del servicio no es válido.',
        ];
    }

    /**
     * Nombres amigables para los campos de formulario.
     *
     * @return array<string, string> Nombres legibles.
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'descripcion' => 'descripción',
            'precio' => 'precio',
            'activo' => 'estado activo',
        ];
    }
}
