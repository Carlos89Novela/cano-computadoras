<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarPermisosUsuarioRequest extends FormRequest
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
        $permisos = $this->input(
            'permisos',
            []
        );

        $motivo = $this->input(
            'motivo_permisos'
        );

        $this->merge([
            'permisos' => is_array($permisos)
                ? $permisos
                : [],
            'motivo_permisos' => is_string($motivo)
                    ? trim($motivo)
                    : $motivo,
        ]);
    }

    public function rules(): array
    {
        $permisosDelegables =
            $this->permisosDelegables();

        return [
            'permisos' => [
                'present',
                'array',
            ],
            'permisos.*' => [
                'string',
                Rule::in($permisosDelegables),
            ],
            'motivo_permisos' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'permisos.present' => 'No fue posible identificar los permisos seleccionados.',
            'permisos.array' => 'Los permisos seleccionados no son válidos.',
            'permisos.*.string' => 'Uno de los permisos seleccionados no es válido.',
            'permisos.*.in' => 'Uno o más permisos no pueden delegarse.',
            'motivo_permisos.required' => 'Debes indicar el motivo del cambio de permisos.',
            'motivo_permisos.string' => 'El motivo debe ser un texto válido.',
            'motivo_permisos.max' => 'El motivo no puede superar los 1000 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'permisos' => 'permisos individuales',
            'motivo_permisos' => 'motivo del cambio de permisos',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function permisosDelegables(): array
    {
        $grupos = config(
            'access_control.permisos_delegables',
            []
        );

        if (! is_array($grupos)) {
            return [];
        }

        return collect($grupos)
            ->flatMap(
                function (mixed $grupo): array {
                    if (
                        ! is_array($grupo)
                        || ! isset($grupo['permisos'])
                        || ! is_array(
                            $grupo['permisos']
                        )
                    ) {
                        return [];
                    }

                    return array_keys(
                        $grupo['permisos']
                    );
                }
            )
            ->filter(
                fn (mixed $permiso): bool => is_string($permiso)
            )
            ->values()
            ->all();
    }
}
