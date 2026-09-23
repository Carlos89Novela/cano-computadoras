<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarRolUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $usuario = $this->route('usuario');

        return $actor instanceof User
            && $usuario instanceof User
            && $actor->hasRole('administrador')
            && $actor->esPropietario()
            && ! $usuario->esPropietario()
            && (int) $actor->id !== (int) $usuario->id;
    }

    protected function prepareForValidation(): void
    {
        $rol = $this->input('rol');
        $motivo = $this->input('motivo');

        $this->merge([
            'rol' => is_string($rol)
                ? trim($rol)
                : $rol,
            'motivo' => is_string($motivo)
                ? trim($motivo)
                : $motivo,
        ]);
    }

    public function rules(): array
    {
        $rolesAsignables = config(
            'access_control.roles_asignables',
            []
        );

        return [
            'rol' => [
                'required',
                'string',
                Rule::in(
                    is_array($rolesAsignables)
                        ? $rolesAsignables
                        : []
                ),
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
            'rol.required' => 'Debes seleccionar un rol.',
            'rol.in' => 'El rol seleccionado no puede asignarse.',
            'motivo.required' => 'Debes indicar el motivo del cambio de rol.',
            'motivo.string' => 'El motivo debe ser un texto válido.',
            'motivo.max' => 'El motivo no puede superar los 1000 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'rol' => 'rol base',
            'motivo' => 'motivo del cambio',
        ];
    }
}
