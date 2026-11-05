<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de Validación para el Alta de Servicios en el Catálogo de Taller.
 *
 * Valida los parámetros requeridos para incorporar un nuevo servicio al catálogo comercial:
 * - Nombre único y descriptivo para evitar duplicados en la selección de presupuestos.
 * - Importe base monetario numérico no negativo y acotado al rango admitido por base de datos.
 * - Estado booleano de disponibilidad operativa inmediata (`activo`).
 */
class StoreServicioRequest extends FormRequest
{
    /**
     * Determina si el usuario tiene autorización para crear servicios.
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
     * Define las reglas de validación para el nuevo servicio.
     *
     * @return array<string, mixed> Reglas de validación aplicables.
     */
    public function rules(): array
    {
        return [
            // Nombre del servicio con verificación de unicidad en la tabla servicios
            'nombre' => [
                'required',
                'string',
                'max:150',
                Rule::unique('servicios', 'nombre'),
            ],
            // Descripción detallada del alcance técnico del servicio (opcional)
            'descripcion' => [
                'nullable',
                'string',
                'max:2000',
            ],
            // Precio base o tarifa sugerida para el servicio
            'precio' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            // Bandera de disponibilidad para nuevas órdenes de servicio
            'activo' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados para la creación de servicios.
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
     * Nombres amigables para los campos del formulario.
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
