<?php

namespace App\Policies;

use App\Models\Equipo;
use App\Models\User;

class EquipoPolicy
{
    public function view(User $user, Equipo $equipo): bool
    {
        return $this->esPropietario($user, $equipo);
    }

    public function update(User $user, Equipo $equipo): bool
    {
        return $this->esPropietario($user, $equipo);
    }

    public function delete(User $user, Equipo $equipo): bool
    {
        return $this->esPropietario($user, $equipo);
    }

    private function esPropietario(
        User $user,
        Equipo $equipo
    ): bool {
        return (int) $equipo->user_id === (int) $user->id;
    }
}
