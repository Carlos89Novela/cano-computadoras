<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de Validación para el Cambio de Rol Base de Usuarios.
 *
 * Controla el cambio de perfil o rol de seguridad (Spatie) para cuentas de usuario:
 * - Exclusividad del Propietario: Solo el administrador propietario puede cambiar roles.
 * - Inmunidad de Propietarios y Auto-cambio: Impide degradar o alterar a otros propietarios
 *   o auto-modificar el rol propio para garantizar continuidad operativa y evitar bloqueos.
 * - Lista Blanca de Roles Asignables: Solo admite roles configurados en
 *   `access_control.roles_asignables` (ej. administrador, supervisor, empleado, cliente).
 * - Justificación Obligatoria: Exige documentar el motivo del cambio de rol para auditoría.
 */
class CambiarRolUsuarioRequest extends FormRequest
{
    /**
     * Valida que el actor sea propietario y que el usuario objetivo no sea propietario ni él mismo.
     *
     * @return bool Verdadero si se satisfacen las políticas jerárquicas de seguridad.
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
     * Recorta los campos de entrada antes de la validación.
     */
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

    /**
     * Define las reglas de validación para el nuevo rol y la justificación.
     *
     * @return array<string, mixed> Reglas de validación aplicables.
     */
    public function rules(): array
    {
        $rolesAsignables = config(
            'access_control.roles_asignables',
            []
        );

        return [
            // El rol debe pertenecer a la lista de roles permitidos del sistema
            'rol' => [
                'required',
                'string',
                Rule::in(
                    is_array($rolesAsignables)
                        ? $rolesAsignables
                        : []
                ),
            ],
            // Motivo o justificativo del cambio para la bitácora de auditoría
            'motivo' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados para el cambio de rol.
     *
     * @return array<string, string> Mensajes legibles.
     */
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

    /**
     * Nombres amigables para los campos de validación de rol.
     *
     * @return array<string, string> Nombres legibles.
     */
    public function attributes(): array
    {
        return [
            'rol' => 'rol base',
            'motivo' => 'motivo del cambio',
        ];
    }
}

