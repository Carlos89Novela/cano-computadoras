<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'es_propietario',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'es_propietario' => 'boolean',
        ];
    }

    /**
     * Equipos relacionados con el usuario.
     *
     * @return HasMany<Equipo, $this>
     */
    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    /**
     * Órdenes de servicio relacionadas con el usuario.
     *
     * @return HasMany<OrdenServicio, $this>
     */
    public function ordenesServicio(): HasMany
    {
        return $this->hasMany(OrdenServicio::class);
    }

    /**
     * Asignaciones en las que el usuario participa como empleado.
     *
     * @return HasMany<OrdenAsignacion, $this>
     */
    public function asignacionesComoEmpleado(): HasMany
    {
        return $this->hasMany(
            OrdenAsignacion::class,
            'empleado_id'
        );
    }

    /**
     * Asignaciones realizadas por el usuario.
     *
     * @return HasMany<OrdenAsignacion, $this>
     */
    public function asignacionesRealizadas(): HasMany
    {
        return $this->hasMany(
            OrdenAsignacion::class,
            'asignado_por_id'
        );
    }

    public function esPropietario(): bool
    {
        return $this->es_propietario === true;
    }

    /**
     * @return BelongsToMany<Sucursal, $this>
     */
    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(
            Sucursal::class,
            'sucursal_usuario'
        )
            ->withPivot([
                'es_principal',
                'es_gerente',
                'activo',
                'asignado_por_id',
                'asignado_at',
                'finalizado_at',
            ])
            ->withTimestamps();
    }

    public function perteneceASucursal(
        Sucursal $sucursal
    ): bool {
        return $this->sucursales()
            ->whereKey($sucursal->id)
            ->wherePivot('activo', true)
            ->exists();
    }
}
