<?php

namespace App\Http\Requests\Proveedores;

use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest para cambiar el estado operativo (activar / desactivar) de un proveedor.
 *
 * Responsabilidades:
 * 1. Autorización: Verifica que el usuario cuente con el permiso 'productos.cambiar_estado'.
 * 2. Comprobación de límites: Asegura que el proveedor pertenezca a la empresa de la ruta.
 * 3. Normalización: Convierte el campo 'activar' a un booleano estricto y limpia espacios en 'motivo'.
 * 4. Validación: Exige que el motivo no esté vacío y no supere los 1000 caracteres.
 */
class CambiarEstadoProveedorRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado tiene permiso para cambiar el estado del proveedor.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        $empresa = $this->route('empresa');
        $proveedor = $this->route('proveedor');

        // Valida que el actor sea un usuario válido, los modelos existan,
        // el proveedor pertenezca a la empresa y el usuario tenga el permiso requerido.
        return $actor instanceof User
            && $empresa instanceof Empresa
            && $proveedor instanceof Proveedor
            && (int) $proveedor->empresa_id === (int) $empresa->id
            && $actor->can('productos.cambiar_estado');
    }

    /**
     * Prepara los datos antes de la validación.
     */
    protected function prepareForValidation(): void
    {
        $motivo = $this->input('motivo');

        // Convierte 'activar' a booleano nativo (soporta "1", "true", 1, true, etc.) y sanea el texto del motivo
        $this->merge([
            'activar' => $this->boolean('activar'),
            'motivo' => is_string($motivo)
                ? trim($motivo)
                : $motivo,
        ]);
    }

    /**
     * Reglas de validación aplicables.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Indica si se debe activar (true) o desactivar (false)
            'activar' => [
                'required',
                'boolean',
            ],
            // Justificación obligatoria para fines de auditoría interna
            'motivo' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados para validación fallida.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'activar.required' => 'Debes indicar el nuevo estado del proveedor.',
            'activar.boolean' => 'El estado seleccionado no es válido.',
            'motivo.required' => 'Debes indicar el motivo del cambio de estado.',
            'motivo.string' => 'El motivo debe ser un texto válido.',
            'motivo.max' => 'El motivo no puede superar los 1000 caracteres.',
        ];
    }

    /**
     * Nombres amigables para los campos en caso de error.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'activar' => 'estado del proveedor',
            'motivo' => 'motivo del cambio de estado',
        ];
    }
}
