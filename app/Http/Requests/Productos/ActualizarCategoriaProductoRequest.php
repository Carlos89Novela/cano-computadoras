<?php

namespace App\Http\Requests\Productos;

use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de Validación para la Actualización de Categorías de Productos.
 *
 * Aplica los controles de seguridad multitenant y unicidad empresarial:
 * - Aislamiento Multitenant: Verifica que la categoría pertenezca inequívocamente
 *   a la empresa vinculada en la ruta y que el actor posea el permiso `productos.actualizar`.
 * - Unicidad por Empresa: Asegura que el nuevo nombre no colisione con otra categoría
 *   dentro de la misma empresa, ignorando el identificador de la categoría en edición.
 * - Normalización de cadenas de texto.
 */
class ActualizarCategoriaProductoRequest extends FormRequest
{
    /**
     * Determina si el actor tiene autorización para modificar categorías en esta empresa.
     *
     * @return bool Verdadero si el usuario tiene permiso y se respeta el aislamiento multitenant.
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
            && $actor->can('productos.actualizar');
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
     * Define las reglas de validación para la categoría.
     *
     * @return array<string, mixed> Reglas de validación aplicables.
     */
    public function rules(): array
    {
        $empresa = $this->route('empresa');
        $categoria = $this->route('categoria');

        return [
            // Nombre de la categoría con unicidad restringida al ámbito de la empresa
            'nombre' => [
                'required',
                'string',
                'max:150',
                Rule::unique(
                    'categorias_producto',
                    'nombre'
                )
                    ->where(
                        fn ($consulta) => $consulta->where(
                            'empresa_id',
                            $empresa instanceof Empresa
                                ? $empresa->id
                                : 0
                        )
                    )
                    ->ignore(
                        $categoria instanceof CategoriaProducto
                            ? $categoria->id
                            : null
                    ),
            ],
            // Descripción técnica o comercial de la categoría (opcional)
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
