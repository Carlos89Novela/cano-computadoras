<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Usuarios\ActivarSuplenciaSupervisor;
use App\Actions\Usuarios\ActualizarPermisosUsuario;
use App\Actions\Usuarios\CambiarRolUsuario;
use App\Actions\Usuarios\RestaurarSuplenciaSupervisor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActualizarPermisosUsuarioRequest;
use App\Http\Requests\Admin\CambiarRolUsuarioRequest;
use App\Models\Empresa;
use App\Models\SuplenciaRol;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Controlador Administrativo de Usuarios, Roles, Permisos y Suplencias Temporales.
 *
 * Aísla el listado de usuarios, los candidatos a suplencia y las suplencias
 * activas al contexto multi-tenant de la empresa activa del administrador/gerente.
 */
class UsuarioController extends Controller
{
    /**
     * Lista usuarios de la empresa activa, filtros por rol/búsqueda y suplencias vigentes.
     */
    public function index(Request $request): View
    {
        /** @var User|null $actor */
        $actor = $request->user();
        $empresaActiva = $this->resolverEmpresaActiva($request, $actor);
        $empresaId = $empresaActiva?->id;

        $busqueda = trim((string) $request->query('buscar', ''));
        $rol = trim((string) $request->query('rol', ''));

        // Consulta base aislada a los usuarios asignados a sucursales de la empresa activa
        $consultaBaseEmpresa = User::query()
            ->when(
                $empresaId !== null,
                function (Builder $query) use ($empresaId): void {
                    $query->whereHas('sucursales', function (Builder $sub) use ($empresaId): void {
                        $sub->where('sucursales.empresa_id', $empresaId);
                    });
                }
            );

        $usuarios = (clone $consultaBaseEmpresa)
            ->with([
                'roles:id,name',
                'permissions:id,name',
            ])
            ->when(
                $busqueda !== '',
                function (Builder $consulta) use ($busqueda): void {
                    $consulta->where(function (Builder $filtro) use ($busqueda): void {
                        $filtro
                            ->where('name', 'like', '%'.$busqueda.'%')
                            ->orWhere('email', 'like', '%'.$busqueda.'%');
                    });
                }
            )
            ->when(
                $rol !== '',
                function (Builder $consulta) use ($rol): void {
                    $consulta->role($rol);
                }
            )
            ->orderByDesc('es_propietario')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $roles = Role::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        // Supervisores actuales pertenecientes únicamente a la empresa activa
        $supervisores = (clone $consultaBaseEmpresa)
            ->role('supervisor')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Colaboradores de la empresa activa elegibles para cubrir temporalmente supervisión
        $candidatosSuplencia = (clone $consultaBaseEmpresa)
            ->where('es_propietario', false)
            ->whereHas('roles', function (Builder $q): void {
                $q->whereNotIn('name', ['administrador', 'supervisor', 'cliente']);
            })
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Suplencias temporales actualmente vigentes en la empresa activa
        $suplenciasActivas = SuplenciaRol::query()
            ->activas()
            ->when(
                $empresaId !== null,
                fn (Builder $q) => $q->where('empresa_id', $empresaId)
            )
            ->with([
                'suplente:id,name,email',
                'supervisorAusente:id,name,email',
                'autorizadoPor:id,name',
            ])
            ->latest('id')
            ->get();

        /** @var view-string $vista */
        $vista = 'admin.usuarios.index';

        return view(
            $vista,
            compact(
                'empresaActiva',
                'usuarios',
                'roles',
                'busqueda',
                'rol',
                'supervisores',
                'candidatosSuplencia',
                'suplenciasActivas'
            )
        );
    }

    /**
     * Muestra el detalle de edición de rol y permisos directos de un usuario
     * filtrando permisos exclusivos de taller cuando la empresa es restaurante.
     */
    public function edit(Request $request, User $usuario): View
    {
        $this->verificarUsuarioEnEmpresaActiva($request, $usuario);

        $usuario->load([
            'roles:id,name',
            'permissions:id,name',
        ]);

        $rolesAsignables = Role::query()
            ->whereIn('name', config('access_control.roles_asignables', []))
            ->orderBy('name')
            ->get(['id', 'name']);

        $gruposPermisos = config('access_control.permisos_delegables', []);
        if (! is_array($gruposPermisos)) {
            $gruposPermisos = [];
        }

        // Detectar giro de la empresa activa para excluir permisos exclusivos de taller en restaurantes
        $empresaActiva = $this->resolverEmpresaActiva($request, $request->user());
        $giro = strtolower((string) ($empresaActiva?->giro_comercial ?? ''));
        $esRestaurante = ($empresaActiva instanceof Empresa && method_exists($empresaActiva, 'esRestaurante') && $empresaActiva->esRestaurante())
            || in_array($giro, ['restaurantes_alimentos', 'restaurante_cafeteria'], true);

        if ($esRestaurante) {
            $gruposExcluidos = config('access_control.grupos_exclusivos_taller', ['ordenes', 'servicios']);
            $permisosExcluidos = config('access_control.permisos_exclusivos_taller', []);

            $gruposFiltrados = [];
            foreach ($gruposPermisos as $claveGrupo => $grupo) {
                if (in_array((string) $claveGrupo, $gruposExcluidos, true)) {
                    continue;
                }

                $permisosGrupo = $grupo['permisos'] ?? [];
                if (is_array($permisosGrupo)) {
                    $permisosGrupo = array_filter(
                        $permisosGrupo,
                        fn (string $nombrePermiso): bool => ! in_array($nombrePermiso, $permisosExcluidos, true),
                        ARRAY_FILTER_USE_KEY
                    );
                }

                $grupo['permisos'] = $permisosGrupo;
                $gruposFiltrados[$claveGrupo] = $grupo;
            }

            $gruposPermisos = $gruposFiltrados;
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

    /**
     * Actualiza el rol base de un usuario de la empresa activa mediante `CambiarRolUsuario`.
     */
    public function updateRole(
        CambiarRolUsuarioRequest $request,
        User $usuario,
        CambiarRolUsuario $cambiarRolUsuario
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $this->verificarUsuarioEnEmpresaActiva($request, $usuario);

        $datos = $request->validated();

        $cambiarRolUsuario->ejecutar(
            actor: $actor,
            usuario: $usuario,
            nuevoRol: (string) $datos['rol'],
            motivo: (string) $datos['motivo'],
            request: $request
        );

        return redirect()
            ->route('admin.usuarios.edit', ['usuario' => $usuario->id])
            ->with('success', 'El rol del usuario fue actualizado correctamente.');
    }

    /**
     * Actualiza los permisos directos individuales de un usuario de la empresa activa.
     */
    public function updatePermissions(
        ActualizarPermisosUsuarioRequest $request,
        User $usuario,
        ActualizarPermisosUsuario $actualizarPermisos
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $this->verificarUsuarioEnEmpresaActiva($request, $usuario);

        $datos = $request->validated();
        $permisos = $datos['permisos'] ?? [];
        abort_unless(is_array($permisos), 422);

        $permisosNormalizados = collect($permisos)
            ->filter(fn (mixed $permiso): bool => is_string($permiso))
            ->values()
            ->all();

        $motivo = $datos['motivo_permisos'] ?? '';
        abort_unless(is_string($motivo), 422);

        $actualizarPermisos->ejecutar(
            actor: $actor,
            usuario: $usuario,
            permisos: $permisosNormalizados,
            motivo: $motivo,
            request: $request
        );

        return redirect()
            ->route('admin.usuarios.edit', ['usuario' => $usuario->id])
            ->with('success', 'Los permisos del usuario fueron actualizados correctamente.');
    }

    /**
     * Activa una suplencia temporal de supervisor dentro de la empresa activa.
     */
    public function activarSuplencia(
        Request $request,
        ActivarSuplenciaSupervisor $activarSuplencia
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $empresaActiva = $this->resolverEmpresaActiva($request, $actor);

        $validated = $request->validate([
            'supervisor_ausente_id' => ['nullable', 'integer', 'exists:users,id'],
            'suplente_id' => ['required', 'integer', 'exists:users,id'],
            'motivo' => ['required', 'string', 'max:500'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin_estimada' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ]);

        /** @var User $suplente */
        $suplente = User::query()->findOrFail((int) $validated['suplente_id']);
        $this->verificarUsuarioEnEmpresaActiva($request, $suplente);

        if (! empty($validated['supervisor_ausente_id'])) {
            $ausente = User::query()->findOrFail((int) $validated['supervisor_ausente_id']);
            $this->verificarUsuarioEnEmpresaActiva($request, $ausente);
        }

        $suplencia = $activarSuplencia->ejecutar(
            actor: $actor,
            suplente: $suplente,
            datos: [
                'empresa_id' => $empresaActiva?->id,
                'supervisor_ausente_id' => $validated['supervisor_ausente_id'] ?? null,
                'motivo' => $validated['motivo'],
                'fecha_inicio' => $validated['fecha_inicio'] ?? now()->toDateString(),
                'fecha_fin_estimada' => $validated['fecha_fin_estimada'] ?? null,
            ],
            request: $request
        );

        return redirect()
            ->route('admin.usuarios.index')
            ->with(
                'success',
                "Suplencia activada: {$suplente->name} ahora tiene permisos temporales de Supervisor (rol respaldado: {$suplencia->rol_anterior})."
            );
    }

    /**
     * Finaliza una suplencia temporal activa y devuelve al colaborador a su rol anterior.
     */
    public function restaurarSuplencia(
        Request $request,
        SuplenciaRol $suplencia,
        RestaurarSuplenciaSupervisor $restaurarSuplencia
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $empresaActiva = $this->resolverEmpresaActiva($request, $actor);
        if ($empresaActiva !== null && $suplencia->empresa_id !== null && (int) $suplencia->empresa_id !== (int) $empresaActiva->id) {
            abort(403, 'Esta suplencia pertenece a otra empresa.');
        }

        $validated = $request->validate([
            'nota_cierre' => ['nullable', 'string', 'max:500'],
        ]);

        $suplenciaCerrada = $restaurarSuplencia->ejecutar(
            actor: $actor,
            suplencia: $suplencia,
            notaCierre: $validated['nota_cierre'] ?? null,
            request: $request
        );

        return redirect()
            ->route('admin.usuarios.index')
            ->with(
                'success',
                "Suplencia finalizada: {$suplenciaCerrada->suplente->name} regresó a su rol anterior ({$suplenciaCerrada->rol_anterior})."
            );
    }

    /**
     * Resuelve la empresa activa desde sesión o sucursal asignada al usuario.
     */
    protected function resolverEmpresaActiva(Request $request, ?User $usuario): ?Empresa
    {
        $sessionEmpresaId = $request->session()->get('empresa_activa_id')
            ?? $request->session()->get('empresa_id');

        if (! empty($sessionEmpresaId)) {
            $empresa = Empresa::query()->find((int) $sessionEmpresaId);
            if ($empresa instanceof Empresa) {
                return $empresa;
            }
        }

        if ($usuario && method_exists($usuario, 'sucursales')) {
            $sucursalActiva = $usuario->sucursales()
                ->wherePivot('activo', true)
                ->orderByDesc('sucursal_usuario.es_principal')
                ->with('empresa')
                ->first();

            if ($sucursalActiva?->empresa instanceof Empresa) {
                $request->session()->put('empresa_activa_id', $sucursalActiva->empresa->id);

                return $sucursalActiva->empresa;
            }
        }

        return Empresa::query()->where('activo', true)->first();
    }

    /**
     * Garantiza que el usuario objetivo pertenezca a la misma empresa activa del actor.
     */
    protected function verificarUsuarioEnEmpresaActiva(Request $request, User $usuarioObjetivo): void
    {
        $empresaActiva = $this->resolverEmpresaActiva($request, $request->user());
        if (! $empresaActiva instanceof Empresa) {
            return;
        }

        $pertenece = $usuarioObjetivo->sucursales()
            ->where('sucursales.empresa_id', $empresaActiva->id)
            ->exists();

        abort_unless($pertenece, 403, 'El usuario no pertenece a la empresa activa.');
    }
}