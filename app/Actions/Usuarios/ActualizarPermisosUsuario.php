<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\Auditoria\RegistrarAuditoria;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class ActualizarPermisosUsuario
{
    public function __construct(
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    /**
     * @param  array<int, string>  $permisos
     */
    public function ejecutar(
        User $actor,
        User $usuario,
        array $permisos,
        string $motivo,
        ?Request $request = null
    ): User {
        $motivo = trim($motivo);

        $permisosNormalizados = collect($permisos)
            ->map(
                fn (string $permiso): string => trim($permiso)
            )
            ->filter(
                fn (string $permiso): bool => $permiso !== ''
            )
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->validar(
            $actor,
            $usuario,
            $permisosNormalizados,
            $motivo
        );

        $request ??= request();

        return DB::transaction(
            function () use (
                $actor,
                $usuario,
                $permisosNormalizados,
                $motivo,
                $request
            ): User {
                $usuarioBloqueado = User::query()
                    ->lockForUpdate()
                    ->findOrFail($usuario->id);

                $this->validar(
                    $actor,
                    $usuarioBloqueado,
                    $permisosNormalizados,
                    $motivo
                );

                $permisosAnteriores = $usuarioBloqueado
                    ->getDirectPermissions()
                    ->pluck('name')
                    ->sort()
                    ->values()
                    ->all();

                $permisosModelos = Permission::query()
                    ->where('guard_name', 'web')
                    ->whereIn(
                        'name',
                        $permisosNormalizados
                    )
                    ->get();

                if (
                    $permisosModelos->count()
                    !== count($permisosNormalizados)
                ) {
                    throw ValidationException::withMessages([
                        'permisos' => 'Uno o más permisos seleccionados no existen.',
                    ]);
                }

                $usuarioBloqueado->syncPermissions(
                    $permisosModelos
                );

                $permisosNuevos = $usuarioBloqueado
                    ->fresh()
                    ->getDirectPermissions()
                    ->pluck('name')
                    ->sort()
                    ->values()
                    ->all();

                $permisosConcedidos = array_values(
                    array_diff(
                        $permisosNuevos,
                        $permisosAnteriores
                    )
                );

                $permisosRetirados = array_values(
                    array_diff(
                        $permisosAnteriores,
                        $permisosNuevos
                    )
                );

                $this->registrarAuditoria->registrar(
                    accion: 'usuario.permisos_actualizados',
                    modulo: 'usuarios',
                    descripcion: 'Se actualizaron los permisos individuales de un usuario.',
                    actor: $actor,
                    modelo: $usuarioBloqueado,
                    usuarioAfectado: $usuarioBloqueado,
                    valoresAnteriores: [
                        'permisos_directos' => $permisosAnteriores,
                    ],
                    valoresNuevos: [
                        'permisos_directos' => $permisosNuevos,
                    ],
                    metadatos: [
                        'permisos_concedidos' => $permisosConcedidos,
                        'permisos_retirados' => $permisosRetirados,
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

    /**
     * @param  array<int, string>  $permisos
     */
    private function validar(
        User $actor,
        User $usuario,
        array $permisos,
        string $motivo
    ): void {
        if (
            ! $actor->hasRole('administrador')
            || ! $actor->esPropietario()
        ) {
            throw new AuthorizationException(
                'Solo el propietario puede asignar permisos.'
            );
        }

        if ((int) $actor->id === (int) $usuario->id) {
            throw new AuthorizationException(
                'El propietario no puede modificar sus propios permisos.'
            );
        }

        if ($usuario->esPropietario()) {
            throw new AuthorizationException(
                'No se pueden modificar los permisos del propietario.'
            );
        }

        $permisosDelegables =
            $this->permisosDelegables();

        $permisosInvalidos = array_diff(
            $permisos,
            $permisosDelegables
        );

        if ($permisosInvalidos !== []) {
            throw ValidationException::withMessages([
                'permisos' => 'Uno o más permisos no pueden delegarse.',
            ]);
        }

        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo_permisos' => 'Debes indicar el motivo del cambio de permisos.',
            ]);
        }

        if (mb_strlen($motivo) > 1000) {
            throw ValidationException::withMessages([
                'motivo_permisos' => 'El motivo no puede superar los 1000 caracteres.',
            ]);
        }
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
