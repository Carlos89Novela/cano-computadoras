<?php

namespace App\Http\Requests\Proveedores;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest para la creación de un nuevo proveedor.
 *
 * Se ejecuta automáticamente antes de llegar al método 'store' del ProveedorController:
 * 1. Comprueba autorización: verifica que el usuario esté autenticado y posea el permiso 'productos.crear'.
 * 2. Normaliza entradas en 'prepareForValidation': elimina espacios redundantes y estandariza mayúsculas/minúsculas.
 * 3. Valida reglas de negocio: código alfanumérico único por empresa, nombre requerido y límites de longitud.
 * 4. Proporciona mensajes de error claros y nombres de atributos en español para la interfaz de usuario.
 */
class CrearProveedorRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado tiene autorización para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        $empresa = $this->route('empresa');

        // Requiere usuario autenticado, empresa válida y permiso de creación de productos/proveedores
        return $actor instanceof User
            && $empresa instanceof Empresa
            && $actor->can('productos.crear');
    }

    /**
     * Prepara los datos antes de aplicar las reglas de validación.
     * Limpia espacios y fuerza mayúsculas en código y RFC, y minúsculas en correo.
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

        // Aplica trim a todos los campos tipo string
        foreach ($campos as $campo) {
            $valor = $this->input($campo);

            $normalizados[$campo] = is_string($valor)
                ? trim($valor)
                : $valor;
        }

        // Fuerza el código a mayúsculas para evitar diferencias por formato
        if (
            is_string($normalizados['codigo'])
        ) {
            $normalizados['codigo'] = mb_strtoupper(
                $normalizados['codigo']
            );
        }

        // Fuerza el RFC a mayúsculas según el estándar fiscal
        if (
            is_string($normalizados['rfc'])
        ) {
            $normalizados['rfc'] = mb_strtoupper(
                $normalizados['rfc']
            );
        }

        // Normaliza el correo electrónico a minúsculas
        if (
            is_string($normalizados['correo'])
        ) {
            $normalizados['correo'] = mb_strtolower(
                $normalizados['correo']
            );
        }

        // Fusiona los datos normalizados dentro de la petición
        $this->merge($normalizados);
    }

    /**
     * Reglas de validación que se aplican a los campos de la petición.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $empresa = $this->route('empresa');

        return [
            // Código: Obligatorio, hasta 40 caracteres, solo alfanumérico y guiones, único dentro de la misma empresa
            'codigo' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique(
                    'proveedores',
                    'codigo'
                )->where(
                    fn ($consulta) => $consulta->where(
                        'empresa_id',
                        $empresa instanceof Empresa
                            ? $empresa->id
                            : 0
                    )
                ),
            ],
            // Nombre comercial: Obligatorio y hasta 200 caracteres
            'nombre' => [
                'required',
                'string',
                'max:200',
            ],
            // Campos opcionales con límites de longitud
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
            // Correo con validación de sintaxis de email
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
     * Mensajes de error personalizados para las validaciones fallidas.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.required' => 'El código del proveedor es obligatorio.',
            'codigo.regex' => 'El código solo puede contener letras, números y guiones.',
            'codigo.max' => 'El código no puede superar los 40 caracteres.',
            'codigo.unique' => 'Ya existe un proveedor con ese código.',
            'nombre.required' => 'El nombre del proveedor es obligatorio.',
            'nombre.max' => 'El nombre no puede superar los 200 caracteres.',
            'razon_social.max' => 'La razón social no puede superar los 200 caracteres.',
            'rfc.max' => 'El RFC no puede superar los 20 caracteres.',
            'contacto.max' => 'El contacto no puede superar los 150 caracteres.',
            'telefono.max' => 'El teléfono no puede superar los 30 caracteres.',
            'correo.email' => 'El correo del proveedor no es válido.',
            'correo.max' => 'El correo no puede superar los 255 caracteres.',
            'direccion.max' => 'La dirección no puede superar los 2000 caracteres.',
            'notas.max' => 'Las notas no pueden superar los 4000 caracteres.',
        ];
    }

    /**
     * Nombres legibles en español para los atributos validados.
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
