<?php

namespace App\Actions\Empresas;

use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Acción de Aprovisionamiento Multi-Tenant: Estructura Base por Empresa.
 *
 * Al crearse una empresa, garantiza la existencia de su estructura jerárquica
 * y operativa acorde a su `giro_comercial`:
 * 1. Gerente / Administrador Propietario (`role = administrador`, `es_propietario = true`).
 * 2. Gerente / Administrador Operativo (`role = administrador`, `es_propietario = false`).
 * 3. Supervisor (`role = supervisor`, `es_propietario = false`).
 * 4. Personal operativo según el giro (`restaurantes_alimentos` / `restaurante_cafeteria`
 *    vs. taller / reparación de cómputo y tecnología).
 */
class ProvisionarEstructuraEmpresa
{
    /**
     * Provisiona roles, sucursal base y usuarios iniciales para la empresa indicada.
     */
    public function ejecutar(Empresa $empresa): void
    {
        DB::transaction(function () use ($empresa): void {
            // 1. Asegurar que existan los roles base en Spatie ('web')
            $rolesBase = ['administrador', 'supervisor', 'empleado', 'cliente'];
            foreach ($rolesBase as $nombreRol) {
                Role::findOrCreate($nombreRol, 'web');
            }

            // 2. Resolver o crear la sucursal principal de la empresa
            $sucursalPrincipal = Sucursal::query()
                ->where('empresa_id', $empresa->id)
                ->orderByDesc('es_principal')
                ->first();

            if (! $sucursalPrincipal) {
                $codigoBase = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $empresa->nombre) ?: 'EMP', 0, 4));
                $sucursalPrincipal = Sucursal::query()->create([
                    'empresa_id' => $empresa->id,
                    'codigo' => $codigoBase.'-01',
                    'nombre' => 'Matriz '.$empresa->nombre,
                    'direccion' => $empresa->direccion_fiscal ?? 'Domicilio por configurar',
                    'telefono' => $empresa->telefono,
                    'es_principal' => true,
                    'activo' => true,
                ]);
            }

            $slug = Str::slug($empresa->nombre) ?: 'empresa-'.$empresa->id;
            $dominio = $slug.'.cano.local';
            $giro = strtolower((string) ($empresa->giro_comercial ?? 'computo_taller'));
            $esRestaurante = (method_exists($empresa, 'esRestaurante') && $empresa->esRestaurante())
                || in_array($giro, ['restaurantes_alimentos', 'restaurante_cafeteria'], true);

            // 3. Definir jerarquía gerencial y de supervisión común para toda empresa
            $plantillaUsuarios = [
                [
                    'email' => "gerente.propietario@{$dominio}",
                    'name' => "Gerente Propietario ({$empresa->nombre})",
                    'role' => 'administrador',
                    'es_propietario' => true,
                ],
                [
                    'email' => "gerente.operativo@{$dominio}",
                    'name' => "Gerente Operativo ({$empresa->nombre})",
                    'role' => 'administrador',
                    'es_propietario' => false,
                ],
                [
                    'email' => "supervisor@{$dominio}",
                    'name' => "Supervisor ({$empresa->nombre})",
                    'role' => 'supervisor',
                    'es_propietario' => false,
                ],
            ];

            // 4. Agregar personal operativo específico según el giro comercial
            if ($esRestaurante) {
                $plantillaUsuarios[] = [
                    'email' => "mesero.caja@{$dominio}",
                    'name' => "Operación Salón / Caja ({$empresa->nombre})",
                    'role' => 'empleado',
                    'es_propietario' => false,
                ];
            } else {
                $plantillaUsuarios[] = [
                    'email' => "tecnico.taller@{$dominio}",
                    'name' => "Técnico de Servicio ({$empresa->nombre})",
                    'role' => 'empleado',
                    'es_propietario' => false,
                ];
            }

            // 5. Crear o vincular usuarios de forma idempotente y asignar sucursal activa
            foreach ($plantillaUsuarios as $datos) {
                /** @var User $usuario */
                $usuario = User::query()->firstOrCreate(
                    ['email' => $datos['email']],
                    [
                        'name' => $datos['name'],
                        'password' => Hash::make('Password123!'),
                        'role' => $datos['role'],
                        'es_propietario' => $datos['es_propietario'],
                        'email_verified_at' => now(),
                    ]
                );

                // Garantizar sincronización del rol en Spatie sin tocar otros roles ya asignados
                if (! $usuario->hasRole($datos['role'])) {
                    $usuario->assignRole($datos['role']);
                }

                // Vincular a la sucursal principal si aún no está asignado
                if (method_exists($usuario, 'sucursales')) {
                    $yaVinculado = $usuario->sucursales()
                        ->where('sucursales.id', $sucursalPrincipal->id)
                        ->exists();

                    if (! $yaVinculado) {
                        $usuario->sucursales()->attach($sucursalPrincipal->id, [
                            'es_principal' => true,
                            'activo' => true,
                        ]);
                    }
                }
            }
        });
    }
}