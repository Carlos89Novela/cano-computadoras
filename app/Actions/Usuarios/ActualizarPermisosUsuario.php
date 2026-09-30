<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\Auditoria\RegistrarAuditoria;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/**
 * Acción de Seguridad y Control de Acceso: Actualizar Permisos Directos de Usuario.
 *
 * Administra la asignación granular de permisos directos individuales a un usuario,
 * complementando o extendiendo los permisos heredados de sus roles Spatie RBAC.
 *
 * Reglas de seguridad y gobierno:
 * 1. Exclusividad de gobernanza: Solo el Propietario del sistema (`esPropietario()`) con rol 'administrador'
 *    está facultado para otorgar o revocar permisos directos.
 * 2. Inmunidad e integridad del propietario:
 *    - El propietario no puede alterar sus propios permisos individuales.
 *    - Las cuentas de propietarios están blindadas y no pueden ser modificadas por terceros.
 * 3. Catálogo de permisos delegables: Solo se pueden asignar permisos explícitamente listados
 *    en la directiva de configuración `access_control.permisos_delegables`.
 * 4. Cálculo de diferencias (Diffing): Computa de forma exacta la lista de permisos concedidos
 *    y revocados para su inclusión en la auditoría inmutable.
 * 5. Trazabilidad obligatoria: Exige una justificación o motivo por escrito (máximo 1000 caracteres)
 *    y emite el evento 'usuario.permisos_actualizados'.
 */
class ActualizarPermisosUsuario
{
    /**
     * Inyecta el servicio centralizado de auditoría.
     *
     * @param  RegistrarAuditoria  $registrarAuditoria  Servicio de registro en bitácora inmutable.
     */
    public function __construct(
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    /**
     * Sincroniza los permisos directos del usuario y registra la bitácora de auditoría.
     *
     * @param  User  $actor  Usuario propietario que ejecuta la modificación de permisos.
     * @param  User  $usuario  Usuario destinatario de los permisos.
     * @param  array<int, string>  $permisos  Listado de identificadores técnicos de permisos (ej: 'productos.crear').
     * @param  string  $motivo  Justificación operativa o administrativa del cambio.
     * @param  Request|null  $request  Petición HTTP entrante para metadatos contextuales.
     * @return User Instancia del usuario actualizado con roles y permisos frescos.
     *
     * @throws AuthorizationException Si el actor no es propietario, o si se intenta alterar la cuenta del propietario.
     * @throws ValidationException Si algún permiso no es delegable, no existe o si el motivo está vacío.
     */
    public function ejecutar(
        User $actor,
        User $usuario,
        array $permisos,
        string $motivo,
        ?Request $request = null
    ): User {
        // Normalización del motivo y de la lista de permisos (sin espacios, sin duplicados y ordenados)
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

        // Validación inicial previa a la transacción
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
                // Bloqueo pesimista del usuario receptor para evitar concurrencia
                $usuarioBloqueado = User::query()
                    ->lockForUpdate()
                    ->findOrFail($usuario->id);

                // Revalidación sobre el registro bloqueado
                $this->validar(
                    $actor,
                    $usuarioBloqueado,
                    $permisosNormalizados,
                    $motivo
                );

                // Captura de los permisos directos previos a la sincronización
                $permisosAnteriores = $usuarioBloqueado
                    ->getDirectPermissions()
                    ->pluck('name')
                    ->sort()
                    ->values()
                    ->all();

                // Obtención de los modelos Permission en base de datos para el guard 'web'
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

                // Sincronización atómica de los permisos directos mediante Spatie
                $usuarioBloqueado->syncPermissions(
                    $permisosModelos
                );

                // Captura de los permisos directos vigentes tras la sincronización
                $permisosNuevos = $usuarioBloqueado
                    ->fresh()
                    ->getDirectPermissions()
                    ->pluck('name')
                    ->sort()
                    ->values()
                    ->all();

                // Cálculo analítico del delta (altas y bajas de permisos)
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

                // Registro formal en la bitácora inmutable de auditoría
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
     * Aplica reglas de autorización propietaria y restricción de delegabilidad.
     *
     * @param  User  $actor  Usuario ejecutante.
     * @param  User  $usuario  Usuario receptor.
     * @param  array<int, string>  $permisos  Listado normalizado de permisos.
     * @param  string  $motivo  Justificación.
     * @return void
     *
     * @throws AuthorizationException Si el actor no tiene privilegios o intenta alterar su propia cuenta/propietario.
     * @throws ValidationException Si se incluyen permisos no delegables o falta justificación.
     */
    private function validar(
        User $actor,
        User $usuario,
        array $permisos,
        string $motivo
    ): void {
        // Regla 1: Solo el propietario con rol administrador puede delegar permisos
        if (
            ! $actor->hasRole('administrador')
            || ! $actor->esPropietario()
        ) {
            throw new AuthorizationException(
                'Solo el propietario puede asignar permisos.'
            );
        }

        // Regla 2: El propietario no puede auto-asignarse permisos directos
        if ((int) $actor->id === (int) $usuario->id) {
            throw new AuthorizationException(
                'El propietario no puede modificar sus propios permisos.'
            );
        }

        // Regla 3: Las cuentas propietarias son inmunes a modificaciones de permisos
        if ($usuario->esPropietario()) {
            throw new AuthorizationException(
                'No se pueden modificar los permisos del propietario.'
            );
        }

        // Regla 4: Solo pueden asignarse permisos catalogados como delegables
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

        // Regla 5: Justificación de auditoría obligatoria
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
     * Extrae del archivo de configuración `access_control` la lista blanca de permisos delegables.
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
