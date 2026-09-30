<?php

namespace App\Http\Requests\Productos;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de Validación para la Creación de Categorías de Productos.
 *
 * Aplica los controles de autorización y unicidad contextual para nuevas categorías:
 * - Autorización Multitenant: Verifica que el usuario cuente con el permiso `productos.crear`
 *   en el ámbito de la empresa receptora.
 * - Unicidad por Empresa: Garantiza que el nombre de la categoría no se repita
 *   dentro de la misma empresa (permitiendo el mismo nombre en empresas distintas).
 * - Normalización de cadenas de texto de entrada.
 */
class CrearCategoriaProductoRequest extends FormRequest
{
    /**
     * Determina si el actor tiene autorización para registrar categorías en la empresa.
     *
     * @return bool Verdadero si el usuario cuenta con el permiso requerido.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        $empresa = $this->route('empresa');

        return $actor instanceof User
            && $empresa instanceof Empresa
            && $actor->can('productos.crear');
    }

    /**
     * Normaliza los textos de nombre y descripción antes de validar.
     */
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

    /**
     * Define las reglas de validación para la nueva categoría.
     *
     * @return array<string, mixed> Reglas de validación aplicables.
     */
    public function rules(): array
    {
        $empresa = $this->route('empresa');

        return [
            // Nombre de la categoría con comprobación de unicidad por empresa
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
            // Descripción detallada del tipo de productos agrupados (opcional)
            'descripcion' => [
                'nullable',
                'string',
                'max:2000',
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
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'nombre.string' => 'El nombre de la categoría debe ser un texto válido.',
            'nombre.max' => 'El nombre no puede superar los 150 caracteres.',
            'nombre.unique' => 'Ya existe una categoría con ese nombre.',
            'descripcion.string' => 'La descripción debe ser un texto válido.',
            'descripcion.max' => 'La descripción no puede superar los 2000 caracteres.',
        ];
    }

    /**
     * Nombres legibles para los atributos evaluados.
     *
     * @return array<string, string> Nombres amigables.
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre de la categoría',
            'descripcion' => 'descripción',
        ];
    }
}
