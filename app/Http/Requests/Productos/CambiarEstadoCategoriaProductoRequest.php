<?php

namespace App\Http\Requests\Productos;

use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoCategoriaProductoRequest extends FormRequest
{
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'activar' => [
                'required',
                'boolean',
            ],
            'motivo' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
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
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'activar' => 'estado de la categoría',
            'motivo' => 'motivo del cambio de estado',
        ];
    }
}
