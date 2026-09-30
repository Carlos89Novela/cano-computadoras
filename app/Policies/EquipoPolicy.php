<?php

namespace App\Policies;

use App\Models\Equipo;
use App\Models\User;

/**
 * Política de Autorización para la Gestión de Equipos de Clientes.
 *
 * Aplica el principio de propiedad y aislamiento de datos de clientes:
 * - Garantiza que un usuario únicamente pueda consultar, editar o solicitar
 *   la eliminación de los equipos que le pertenecen directamente (`user_id`).
 */
class EquipoPolicy
{
    /**
     * Determina si el usuario puede consultar el detalle del equipo.
     *
     * @param  User  $user  Usuario autenticado.
     * @param  Equipo  $equipo  Equipo a consultar.
     * @return bool Verdadero si el usuario es el dueño del equipo.
     */
    public function view(User $user, Equipo $equipo): bool
    {
        return $this->esPropietario($user, $equipo);
    }

    /**
     * Determina si el usuario puede modificar las características del equipo.
     *
     * @param  User  $user  Usuario autenticado.
     * @param  Equipo  $equipo  Equipo a modificar.
     * @return bool Verdadero si el usuario es el dueño del equipo.
     */
    public function update(User $user, Equipo $equipo): bool
    {
        return $this->esPropietario($user, $equipo);
    }

    /**
     * Determina si el usuario puede eliminar el registro del equipo.
     *
     * @param  User  $user  Usuario autenticado.
     * @param  Equipo  $equipo  Equipo a eliminar.
     * @return bool Verdadero si el usuario es el dueño del equipo.
     */
    public function delete(User $user, Equipo $equipo): bool
    {
        return $this->esPropietario($user, $equipo);
    }

    /**
     * Valida la coincidencia exacta de pertenencia entre el usuario y el equipo.
     *
     * @param  User  $user  Usuario evaluado.
     * @param  Equipo  $equipo  Equipo a verificar.
     * @return bool Verdadero si el identificador de usuario coincide.
     */
    private function esPropietario(
        User $user,
        Equipo $equipo
    ): bool {
        return (int) $equipo->user_id === (int) $user->id;
    }
}
