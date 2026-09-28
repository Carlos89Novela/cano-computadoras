<?php

namespace App\Http\Requests\Productos;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearCategoriaProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $empresa = $this->route('empresa');

        return $actor instanceof User
            && $empresa instanceof Empresa
            && $actor->can('productos.crear');
    }

    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre');
        $descripcion = $this->input('descripcion');

        $this->merge([
            'nombre' => is_string($nombre)
                ? trim($nombre)
                : $nombre,
            'descripcion' => is_string($descripcion)
                ? trim($descripcion)
                : $descripcion,
        ]);
    }

    public function rules(): array
    {
        $empresa = $this->route('empresa');

        return [
            'nombre' => [
                'required',
                'string',
                'max:150',
                Rule::unique(
                    'categorias_producto',
                    'nombre'
                )->where(
                    fn ($consulta) => $consulta->where(
                        'empresa_id',
                        $empresa instanceof Empresa
                            ? $empresa->id
                            : 0
                    )
                ),
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'nombre.string' => 'El nombre de la categoría debe ser un texto válido.',
            'nombre.max' => 'El nombre no puede superar los 150 caracteres.',
            'nombre.unique' => 'Ya existe una categoría con ese nombre.',
            'descripcion.string' => 'La descripción debe ser un texto válido.',
            'descripcion.max' => 'La descripción no puede superar los 2000 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre de la categoría',
            'descripcion' => 'descripción',
        ];
    }
}
