<?php

namespace App\Http\Requests\Proveedores;

use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest para la actualización de un proveedor existente.
 *
 * Responsabilidades:
 * 1. Autorización: Verifica que el usuario tenga el permiso 'productos.actualizar' y que
 *    el proveedor pertenezca a la empresa indicada en la ruta.
 * 2. Normalización de datos en 'prepareForValidation': Aplica trim a las cadenas,
 *    fuerza mayúsculas en 'codigo' y 'rfc', y convierte el 'correo' a minúsculas.
 * 3. Reglas de validación: Comprueba formato de código, campos requeridos y unicidad del
 *    código en la misma empresa ignorando el ID del proveedor actual.
 * 4. Diccionario de mensajes y nombres de atributos amigables en español.
 */
class ActualizarProveedorRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado tiene autorización para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        $empresa = $this->route('empresa');
        $proveedor = $this->route('proveedor');

        return $actor instanceof User
            && $empresa instanceof Empresa
            && $proveedor instanceof Proveedor
            && (int) $proveedor->empresa_id === (int) $empresa->id
            && $actor->can('productos.actualizar');
    }

    /**
     * Prepara los datos antes de aplicar las reglas de validación.
     */
    protected function prepareForValidation(): void
    {
        $campos = [
            'codigo',
            'nombre',
            'razon_social',
            'rfc',
            'contacto',
            'telefono',
            'correo',
            'direccion',
            'notas',
        ];

        $normalizados = [];

        foreach ($campos as $campo) {
            $valor = $this->input($campo);

            $normalizados[$campo] = is_string($valor)
                ? trim($valor)
                : $valor;
        }

        if (is_string($normalizados['codigo'])) {
            $normalizados['codigo'] = mb_strtoupper(
                $normalizados['codigo']
            );
        }

        if (is_string($normalizados['rfc'])) {
            $normalizados['rfc'] = mb_strtoupper(
                $normalizados['rfc']
            );
        }

        if (is_string($normalizados['correo'])) {
            $normalizados['correo'] = mb_strtolower(
                $normalizados['correo']
            );
        }

        $this->merge($normalizados);
    }

    /**
     * Reglas de validación para la actualización del proveedor.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $empresa = $this->route('empresa');
        $proveedor = $this->route('proveedor');

        return [
            // Código: Único por empresa, pero ignorando el ID del proveedor actual para permitir conservar su clave
            'codigo' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique(
                    'proveedores',
                    'codigo'
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
                        $proveedor instanceof Proveedor
                            ? $proveedor->id
                            : null
                    ),
            ],
            'nombre' => [
                'required',
                'string',
                'max:200',
            ],
            'razon_social' => [
                'nullable',
                'string',
                'max:200',
            ],
            'rfc' => [
                'nullable',
                'string',
                'max:20',
            ],
            'contacto' => [
                'nullable',
                'string',
                'max:150',
            ],
            'telefono' => [
                'nullable',
                'string',
                'max:30',
            ],
            'correo' => [
                'nullable',
                'email',
                'max:255',
            ],
            'direccion' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'notas' => [
                'nullable',
                'string',
                'max:4000',
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
            'codigo.required' => 'El código del proveedor es obligatorio.',
            'codigo.string' => 'El código del proveedor debe ser un texto válido.',
            'codigo.regex' => 'El código solo puede contener letras, números y guiones.',
            'codigo.max' => 'El código no puede superar los 40 caracteres.',
            'codigo.unique' => 'Ya existe un proveedor con ese código.',
            'nombre.required' => 'El nombre del proveedor es obligatorio.',
            'nombre.string' => 'El nombre del proveedor debe ser un texto válido.',
            'nombre.max' => 'El nombre no puede superar los 200 caracteres.',
            'razon_social.string' => 'La razón social debe ser un texto válido.',
            'razon_social.max' => 'La razón social no puede superar los 200 caracteres.',
            'rfc.string' => 'El RFC debe ser un texto válido.',
            'rfc.max' => 'El RFC no puede superar los 20 caracteres.',
            'contacto.string' => 'El contacto debe ser un texto válido.',
            'contacto.max' => 'El contacto no puede superar los 150 caracteres.',
            'telefono.string' => 'El teléfono debe ser un texto válido.',
            'telefono.max' => 'El teléfono no puede superar los 30 caracteres.',
            'correo.email' => 'El correo del proveedor no es válido.',
            'correo.max' => 'El correo no puede superar los 255 caracteres.',
            'direccion.string' => 'La dirección debe ser un texto válido.',
            'direccion.max' => 'La dirección no puede superar los 2000 caracteres.',
            'notas.string' => 'Las notas deben ser un texto válido.',
            'notas.max' => 'Las notas no pueden superar los 4000 caracteres.',
        ];
    }

    /**
     * Nombres amigables para los campos validados.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'codigo' => 'código del proveedor',
            'nombre' => 'nombre del proveedor',
            'razon_social' => 'razón social',
            'rfc' => 'RFC',
            'contacto' => 'contacto',
            'telefono' => 'teléfono',
            'correo' => 'correo',
            'direccion' => 'dirección',
            'notas' => 'notas',
        ];
    }
}
