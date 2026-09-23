<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\Auditoria\RegistrarAuditoria;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class CambiarRolUsuario
{
    public function __construct(
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    public function ejecutar(
        User $actor,
        User $usuario,
        string $nuevoRol,
        string $motivo,
        ?Request $request = null
    ): User {
        $nuevoRol = trim($nuevoRol);
        $motivo = trim($motivo);

        $this->validar(
            $actor,
            $usuario,
            $nuevoRol,
            $motivo
        );

        $request ??= request();

        return DB::transaction(
            function () use (
                $actor,
                $usuario,
                $nuevoRol,
                $motivo,
                $request
            ): User {
                $usuarioBloqueado = User::query()
                    ->lockForUpdate()
                    ->findOrFail($usuario->id);

                $this->validar(
                    $actor,
                    $usuarioBloqueado,
                    $nuevoRol,
                    $motivo
                );

                $rolesAnteriores = $usuarioBloqueado
                    ->getRoleNames()
                    ->values()
                    ->all();

                $permisosDirectos = $usuarioBloqueado
                    ->getDirectPermissions()
                    ->pluck('name')
                    ->values()
                    ->all();

                Role::findByName(
                    $nuevoRol,
                    'web'
                );

                $usuarioBloqueado->syncRoles([
                    $nuevoRol,
                ]);

                $rolesNuevos = $usuarioBloqueado
                    ->fresh()
                    ->getRoleNames()
                    ->values()
                    ->all();

                $this->registrarAuditoria->registrar(
                    accion: 'usuario.rol_actualizado',
                    modulo: 'usuarios',
                    descripcion: 'Se actualizó el rol base de un usuario.',
                    actor: $actor,
                    modelo: $usuarioBloqueado,
                    usuarioAfectado: $usuarioBloqueado,
                    valoresAnteriores: [
                        'roles' => $rolesAnteriores,
                        'permisos_directos' => $permisosDirectos,
                    ],
                    valoresNuevos: [
                        'roles' => $rolesNuevos,
                        'permisos_directos' => $permisosDirectos,
                    ],
                    metadatos: [
                        'rol_anterior' => $rolesAnteriores[0] ?? null,
                        'rol_nuevo' => $nuevoRol,
                    ],
                    motivo: $motivo,
                    request: $request
                );

                return $usuarioBloqueado
                    ->fresh()
                    ->load([
                        'roles',
                        'permissions',
                    ]);
            }
        );
    }

    private function validar(
        User $actor,
        User $usuario,
        string $nuevoRol,
        string $motivo
    ): void {
        if (
            ! $actor->hasRole('administrador')
            || ! $actor->esPropietario()
        ) {
            throw new AuthorizationException(
                'Solo el propietario puede cambiar roles.'
            );
        }

        if ((int) $actor->id === (int) $usuario->id) {
            throw new AuthorizationException(
                'El propietario no puede cambiar su propio rol.'
            );
        }

        if ($usuario->esPropietario()) {
            throw new AuthorizationException(
                'No se puede modificar el rol del propietario.'
            );
        }

        $rolesAsignables = config(
            'access_control.roles_asignables',
            []
        );

        if (
            ! is_array($rolesAsignables)
            || ! in_array(
                $nuevoRol,
                $rolesAsignables,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'rol' => 'El rol seleccionado no puede asignarse.',
            ]);
        }

        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Debes indicar el motivo del cambio de rol.',
            ]);
        }

        if (mb_strlen($motivo) > 1000) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo no puede superar los 1000 caracteres.',
            ]);
        }
    }
}
