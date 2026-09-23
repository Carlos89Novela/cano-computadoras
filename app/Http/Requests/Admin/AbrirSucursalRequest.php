<?php

namespace App\Http\Requests\Admin;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AbrirSucursalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $empresa = $this->route('empresa');

        return $actor instanceof User
            && $empresa instanceof Empresa
            && $actor->hasRole('administrador')
            && $actor->esPropietario();
    }

    protected function prepareForValidation(): void
    {
        $campos = [
            'codigo',
            'nombre',
            'telefono',
            'correo',
            'direccion',
            'ciudad',
            'estado',
            'codigo_postal',
            'motivo',
        ];

        $normalizados = [];

        foreach ($campos as $campo) {
            $valor = $this->input($campo);

            $normalizados[$campo] = is_string($valor)
                ? trim($valor)
                : $valor;
        }

        $normalizados['es_principal'] = $this->boolean(
            'es_principal'
        );

        $this->merge($normalizados);
    }

    public function rules(): array
    {
        $empresa = $this->route('empresa');

        return [
            'gerente_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'codigo' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('sucursales', 'codigo')
                    ->where(
                        fn ($consulta) => $consulta->where(
                            'empresa_id',
                            $empresa instanceof Empresa
                                ? $empresa->id
                                : 0
                        )
                    ),
            ],
            'nombre' => [
                'required',
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
                'max:1000',
            ],
            'ciudad' => [
                'nullable',
                'string',
                'max:100',
            ],
            'estado' => [
                'nullable',
                'string',
                'max:100',
            ],
            'codigo_postal' => [
                'nullable',
                'string',
                'max:10',
            ],
            'es_principal' => [
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

    public function messages(): array
    {
        return [
            'gerente_id.required' => 'Debes seleccionar al gerente de la sucursal.',
            'gerente_id.exists' => 'El gerente seleccionado no existe.',
            'codigo.required' => 'El código de la sucursal es obligatorio.',
            'codigo.regex' => 'El código solo puede contener letras, números y guiones.',
            'codigo.unique' => 'Ya existe una sucursal con ese código.',
            'codigo.max' => 'El código no puede superar los 30 caracteres.',
            'nombre.required' => 'El nombre de la sucursal es obligatorio.',
            'nombre.max' => 'El nombre no puede superar los 150 caracteres.',
            'correo.email' => 'El correo de la sucursal no es válido.',
            'motivo.required' => 'Debes indicar el motivo de apertura.',
            'motivo.max' => 'El motivo no puede superar los 1000 caracteres.',
        ];
    }
}
