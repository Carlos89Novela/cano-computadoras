<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\Auditoria\RegistrarAuditoria;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Acción de Seguridad y Control de Acceso: Cambiar Rol Base de Usuario.
 *
 * Reasigna el rol organizativo principal de un usuario en el esquema Spatie RBAC
 * (ej: transicionar de 'empleado' a 'supervisor', o viceversa).
 *
 * Políticas de gobierno y seguridad:
 * 1. Autoridad Propietaria: Solo el Propietario del sistema (`esPropietario()`) con rol 'administrador'
 *    está facultado para redefinir el rol jerárquico de cualquier colaborador.
 * 2. Protección de la cuenta propietaria:
 *    - El propietario no puede cambiar su propio rol (evita el auto-bloqueo o pérdida de gobernanza).
 *    - Las cuentas con estatus propietario son inmunes y no admiten degradación ni alteración de rol.
 * 3. Catálogo de roles permitidos: El nuevo rol debe pertenecer a la lista blanca definida en `access_control.roles_asignables`.
 * 4. Integridad de permisos: Al cambiar el rol base mediante `syncRoles`, se preservan intactos
 *    los permisos directos individuales previamente asignados al usuario.
 * 5. Trazabilidad inmutable: Registra la acción 'usuario.rol_actualizado' detallando el rol anterior y nuevo,
 *    con el motivo especificado por el administrador.
 */
class CambiarRolUsuario
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
     * Ejecuta el cambio de rol base del usuario bajo control de transacciones y auditoría.
     *
     * @param  User  $actor  Usuario propietario que autoriza el cambio.
     * @param  User  $usuario  Usuario sujeto de la modificación.
     * @param  string  $nuevoRol  Nombre del nuevo rol a asignar (ej: 'supervisor', 'empleado').
     * @param  string  $motivo  Explicación u justificación del cambio de rol.
     * @param  Request|null  $request  Petición HTTP entrante para captura de contexto telemático.
     * @return User Instancia del usuario con roles y permisos actualizados.
     *
     * @throws AuthorizationException Si el actor no es propietario o intenta modificar cuentas protegidas.
     * @throws ValidationException Si el rol no es asignable o el motivo es inválido.
     */
    public function ejecutar(
        User $actor,
        User $usuario,
        string $nuevoRol,
        string $motivo,
        ?Request $request = null
    ): User {
        // Normalización y limpieza de cadenas
        $nuevoRol = trim($nuevoRol);
        $motivo = trim($motivo);

        // Validación inicial previa a la transacción
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
                // Bloqueo pesimista del usuario a modificar
                $usuarioBloqueado = User::query()
                    ->lockForUpdate()
                    ->findOrFail($usuario->id);

                // Revalidación sobre el registro bloqueado
                $this->validar(
                    $actor,
                    $usuarioBloqueado,
                    $nuevoRol,
                    $motivo
                );

                // Captura fotográfica de roles y permisos previos
                $rolesAnteriores = $usuarioBloqueado
                    ->getRoleNames()
                    ->values()
                    ->all();

                $permisosDirectos = $usuarioBloqueado
                    ->getDirectPermissions()
                    ->pluck('name')
                    ->values()
                    ->all();

                // Verifica que el rol exista en la base de datos para el guard 'web'
                Role::findByName(
                    $nuevoRol,
                    'web'
                );

                // Asignación atómica del nuevo rol único reemplazando los anteriores
                $usuarioBloqueado->syncRoles([
                    $nuevoRol,
                ]);

                // Captura fotográfica posterior
                $rolesNuevos = $usuarioBloqueado
                    ->fresh()
                    ->getRoleNames()
                    ->values()
                    ->all();

                // Asienta el evento en la bitácora inmutable de auditoría
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

    /**
     * Valida permisos del actor, integridad de la cuenta destino y validez del rol solicitado.
     *
     * @param  User  $actor  Usuario ejecutante.
     * @param  User  $usuario  Usuario a modificar.
     * @param  string  $nuevoRol  Rol solicitado.
     * @param  string  $motivo  Motivo de cambio.
     * @return void
     *
     * @throws AuthorizationException Si el actor no es propietario o intenta modificar una cuenta protegida.
     * @throws ValidationException Si el rol no pertenece a la lista autorizada o falta el motivo.
     */
    private function validar(
        User $actor,
        User $usuario,
        string $nuevoRol,
        string $motivo
    ): void {
        // Regla 1: Solo el Propietario con rol administrador puede alterar roles
        if (
            ! $actor->hasRole('administrador')
            || ! $actor->esPropietario()
        ) {
            throw new AuthorizationException(
                'Solo el propietario puede cambiar roles.'
            );
        }

        // Regla 2: El propietario no puede cambiar su propio rol
        if ((int) $actor->id === (int) $usuario->id) {
            throw new AuthorizationException(
                'El propietario no puede cambiar su propio rol.'
            );
        }

        // Regla 3: Cuentas propietarias blindadas
        if ($usuario->esPropietario()) {
            throw new AuthorizationException(
                'No se puede modificar el rol del propietario.'
            );
        }

        // Regla 4: El rol debe figurar en la lista blanca de roles asignables
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

        // Regla 5: Justificación de auditoría obligatoria
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
