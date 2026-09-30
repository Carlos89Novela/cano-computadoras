<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * Modelo Eloquent que representa a los Usuarios del sistema (Clientes, Empleados, Supervisores, Administradores y Propietario).
 *
 * Implementa autenticación Laravel, verificación de correo electrónico obligatoria (MustVerifyEmail)
 * y el trait HasRoles de Spatie Permission para gestión de roles y permisos delegables.
 *
 * Jerarquía de Propietario (Owner):
 * El atributo 'es_propietario' designa al superusuario de la organización, quien posee de forma
 * exclusiva los permisos reservados (asignación de roles administrativos, modificación de otros propietarios
 * y descarga de auditorías completas) que no pueden ser delegados a administradores comunes.
 *
 * @property int $id
 * @property string $name Nombre completo del usuario
 * @property string $email Correo electrónico único
 * @property string $password Contraseña cifrada
 * @property bool $es_propietario Indicador de máxima jerarquía en el sistema
 * @property Carbon|null $email_verified_at Fecha de verificación de correo
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Columnas asignables en masa.
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
     * Atributos ocultos en serialización JSON (APIs o vistas).
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Conversiones automáticas de tipos de atributos.
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
     * Relación: Equipos registrados pertenecientes a este cliente.
     *
     * @return HasMany<Equipo, $this>
     */
    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    /**
     * Relación: Órdenes de servicio registradas a nombre de este cliente.
     *
     * @return HasMany<OrdenServicio, $this>
     */
    public function ordenesServicio(): HasMany
    {
        return $this->hasMany(OrdenServicio::class);
    }

    /**
     * Relación: Órdenes de servicio donde el usuario fue asignado como empleado técnico.
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
     * Relación: Asignaciones de trabajo emitidas o despachadas por este supervisor/administrador.
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

    /**
     * Comprueba si el usuario tiene el estatus de propietario del sistema.
     */
    public function esPropietario(): bool
    {
        return $this->es_propietario === true;
    }

    /**
     * Relación: Sucursales a las que está asignado el usuario para trabajar.
     *
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

    /**
     * Comprueba si el usuario está asignado y activo en una sucursal determinada.
     */
    public function perteneceASucursal(
        Sucursal $sucursal
    ): bool {
        return $this->sucursales()
            ->whereKey($sucursal->id)
            ->wherePivot('activo', true)
            ->exists();
    }
}
