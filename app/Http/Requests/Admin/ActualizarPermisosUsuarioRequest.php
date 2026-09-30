<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de Validación para la Actualización de Permisos Directos de Usuarios.
 *
 * Aplica salvaguardas estrictas de control de acceso jerárquico y separación de funciones:
 * - Exclusividad del Propietario: Solo un administrador con bandera de propietario puede
 *   delegar o revocar permisos directos.
 * - Inmunidad de Propietarios y Auto-modificación: Impide alterar los permisos de otro propietario
 *   o auto-modificar los propios para evitar escalada de privilegios o auto-bloqueos.
 * - Lista Blanca de Permisos Delegables: Solo admite permisos explícitamente declarados
 *   como delegables en la configuración (`access_control.permisos_delegables`).
 * - Motivo Obligatorio: Todo ajuste en privilegios debe registrar una justificación para auditoría.
 */
class ActualizarPermisosUsuarioRequest extends FormRequest
{
    /**
     * Valida que el actor sea propietario y que el usuario objetivo no sea propietario ni él mismo.
     *
     * @return bool Verdadero si la jerarquía autoriza la modificación de permisos.
     */
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

    /**
     * Asegura la estructura de matriz para permisos y recorta el motivo de cambio.
     */
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

    /**
     * Define las reglas de validación para los permisos delegables y el motivo.
     *
     * @return array<string, mixed> Reglas de validación aplicables.
     */
    public function rules(): array
    {
        $permisosDelegables =
            $this->permisosDelegables();

        return [
            // Arreglo de identificadores de permisos seleccionados
            'permisos' => [
                'present',
                'array',
            ],
            // Cada permiso debe ser una cadena perteneciente a la lista blanca delegable
            'permisos.*' => [
                'string',
                Rule::in($permisosDelegables),
            ],
            // Justificación obligatoria para la bitácora de auditoría
            'motivo_permisos' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados para la delegación de permisos.
     *
     * @return array<string, string> Mensajes de error legibles.
     */
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

    /**
     * Nombres amigables para los atributos en caso de fallos.
     *
     * @return array<string, string> Nombres legibles.
     */
    public function attributes(): array
    {
        return [
            'permisos' => 'permisos individuales',
            'motivo_permisos' => 'motivo del cambio de permisos',
        ];
    }

    /**
     * Extrae y compila la lista blanca de permisos que pueden ser otorgados de forma delegada.
     *
     * @return array<int, string> Lista plana de nombres de permisos autorizados para delegación.
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

