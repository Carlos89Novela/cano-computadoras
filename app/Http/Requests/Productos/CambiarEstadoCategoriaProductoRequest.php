<?php

namespace App\Http\Requests\Productos;

use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitud de Validación para el Cambio de Estado Operativo de Categorías de Productos.
 *
 * Aplica los controles de seguridad multitenant y trazabilidad del motivo de cambio:
 * - Aislamiento Multitenant: Exige que la categoría pertenezca a la empresa de la ruta
 *   y que el actor cuente con el permiso `productos.cambiar_estado`.
 * - Justificación Obligatoria: Requiere documentar el motivo del cambio de estado (reactivación
 *   o desactivación) para mantener la auditoría del catálogo.
 * - Normalización de la bandera booleana `activar`.
 */
class CambiarEstadoCategoriaProductoRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado tiene autorización para cambiar el estado de la categoría.
     *
     * @return bool Verdadero si el usuario cuenta con el permiso en la empresa correspondiente.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        $empresa = $this->route('empresa');
        $categoria = $this->route('categoria');

        return $actor instanceof User
            && $empresa instanceof Empresa
            && $categoria instanceof CategoriaProducto
            && (int) $categoria->empresa_id === (int) $empresa->id
            && $actor->can('productos.cambiar_estado');
    }

    /**
     * Normaliza el booleano de activación y recorta el motivo de cambio.
     */
    protected function prepareForValidation(): void
    {
        $motivo = $this->input('motivo');

        $this->merge([
            'activar' => $this->boolean('activar'),
            'motivo' => is_string($motivo)
                ? trim($motivo)
                : $motivo,
        ]);
    }

    /**
     * Define las reglas de validación para el cambio de estado y motivo.
     *
     * @return array<string, array<int, string>> Reglas de validación aplicables.
     */
    public function rules(): array
    {
        return [
            // Bandera booleana: true para reactivar, false para desactivar
            'activar' => [
                'required',
                'boolean',
            ],
            // Justificación obligatoria para la bitácora de auditoría
            'motivo' => [
                'required',
                'string',
                'max:1000',
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
            'activar.required' => 'Debes indicar el nuevo estado de la categoría.',
            'activar.boolean' => 'El estado seleccionado no es válido.',
            'motivo.required' => 'Debes indicar el motivo del cambio de estado.',
            'motivo.string' => 'El motivo debe ser un texto válido.',
            'motivo.max' => 'El motivo no puede superar los 1000 caracteres.',
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
            'activar' => 'estado de la categoría',
            'motivo' => 'motivo del cambio de estado',
        ];
    }
}

