<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Usuarios\ActualizarPermisosUsuario;
use App\Actions\Usuarios\CambiarRolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActualizarPermisosUsuarioRequest;
use App\Http\Requests\Admin\CambiarRolUsuarioRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    public function index(
        Request $request
    ): View {
        $busqueda = trim(
            (string) $request->query('buscar', '')
        );

        $rol = trim(
            (string) $request->query('rol', '')
        );

        $usuarios = User::query()
            ->with([
                'roles:id,name',
                'permissions:id,name',
            ])
            ->when(
                $busqueda !== '',
                function (
                    Builder $consulta
                ) use ($busqueda): void {
                    $consulta->where(
                        function (
                            Builder $filtro
                        ) use ($busqueda): void {
                            $filtro
                                ->where(
                                    'name',
                                    'like',
                                    '%'.$busqueda.'%'
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    '%'.$busqueda.'%'
                                );
                        }
                    );
                }
            )
            ->when(
                $rol !== '',
                function (
                    Builder $consulta
                ) use ($rol): void {
                    $consulta->role($rol);
                }
            )
            ->orderByDesc('es_propietario')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $roles = Role::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        /** @var view-string $vista */
        $vista = 'admin.usuarios.index';

        return view(
            $vista,
            compact(
                'usuarios',
                'roles',
                'busqueda',
                'rol'
            )
        );
    }

    public function edit(
        User $usuario
    ): View {
        $usuario->load([
            'roles:id,name',
            'permissions:id,name',
        ]);

        $rolesAsignables = Role::query()
            ->whereIn(
                'name',
                config(
                    'access_control.roles_asignables',
                    []
                )
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        $gruposPermisos = config(
            'access_control.permisos_delegables',
            []
        );

        if (! is_array($gruposPermisos)) {
            $gruposPermisos = [];
        }

        $permisosDirectos = $usuario
            ->getDirectPermissions()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $permisosHeredados = $usuario
            ->getPermissionsViaRoles()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        /** @var view-string $vista */
        $vista = 'admin.usuarios.edit';

        return view(
            $vista,
            compact(
                'usuario',
                'rolesAsignables',
                'gruposPermisos',
                'permisosDirectos',
                'permisosHeredados',
            )
        );
    }

    public function updateRole(
        CambiarRolUsuarioRequest $request,
        User $usuario,
        CambiarRolUsuario $cambiarRolUsuario
    ): RedirectResponse {
        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            403
        );

        $datos = $request->validated();

        $cambiarRolUsuario->ejecutar(
            actor: $actor,
            usuario: $usuario,
            nuevoRol: (string) $datos['rol'],
            motivo: (string) $datos['motivo'],
            request: $request
        );

        return redirect()
            ->route(
                'admin.usuarios.edit',
                [
                    'usuario' => $usuario->id,
                ]
            )
            ->with(
                'success',
                'El rol del usuario fue actualizado correctamente.'
            );
    }

    public function updatePermissions(
        ActualizarPermisosUsuarioRequest $request,
        User $usuario,
        ActualizarPermisosUsuario $actualizarPermisos
    ): RedirectResponse {
        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            403
        );

        $datos = $request->validated();

        $permisos = $datos['permisos'] ?? [];

        abort_unless(
            is_array($permisos),
            422
        );

        $permisosNormalizados = collect(
            $permisos
        )
            ->filter(
                fn (mixed $permiso): bool => is_string($permiso)
            )
            ->values()
            ->all();

        $motivo = $datos[
            'motivo_permisos'
        ] ?? '';

        abort_unless(
            is_string($motivo),
            422
        );

        $actualizarPermisos->ejecutar(
            actor: $actor,
            usuario: $usuario,
            permisos: $permisosNormalizados,
            motivo: $motivo,
            request: $request
        );

        return redirect()
            ->route(
                'admin.usuarios.edit',
                [
                    'usuario' => $usuario->id,
                ]
            )
            ->with(
                'success',
                'Los permisos del usuario fueron actualizados correctamente.'
            );
    }
}
