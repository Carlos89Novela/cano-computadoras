<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesYPermisosFlujoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Limpiar caché de permisos de Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Lista de permisos clasificados por área
        $permisos = [
            // Ventas / POS
            'pos.vender',
            'pos.turno.abrir_cerrar',
            'pos.reportes',
            'pos.devoluciones',

            // Compras
            'compras.ver',
            'compras.crear',
            'compras.autorizar',

            // Finanzas
            'finanzas.ver',
            'finanzas.registrar_pago',
            'finanzas.reportes_fiscales',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        // 3. Crear roles y sincronizar permisos

        // POS
        $rolVendedor = Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web']);
        $rolVendedor->syncPermissions(['pos.vender', 'pos.turno.abrir_cerrar']);

        // Supervisor de Ventas: incluye ventas, turnos, reportes y DEVOLUCIONES
        $rolSupervisorVentas = Role::firstOrCreate(['name' => 'supervisor_ventas', 'guard_name' => 'web']);
        $rolSupervisorVentas->syncPermissions([
            'pos.vender',
            'pos.turno.abrir_cerrar',
            'pos.reportes',
            'pos.devoluciones',
        ]);

        // Compras
        $rolComprador = Role::firstOrCreate(['name' => 'comprador', 'guard_name' => 'web']);
        $rolComprador->syncPermissions(['compras.ver', 'compras.crear']);

        $rolGerenteCompras = Role::firstOrCreate(['name' => 'gerente_compras', 'guard_name' => 'web']);
        $rolGerenteCompras->syncPermissions(['compras.ver', 'compras.crear', 'compras.autorizar']);

        // Finanzas
        $rolAuxiliarFinanzas = Role::firstOrCreate(['name' => 'auxiliar_finanzas', 'guard_name' => 'web']);
        $rolAuxiliarFinanzas->syncPermissions(['finanzas.ver', 'finanzas.registrar_pago']);

        $rolGerenteFinanzas = Role::firstOrCreate(['name' => 'gerente_finanzas', 'guard_name' => 'web']);
        $rolGerenteFinanzas->syncPermissions(['finanzas.ver', 'finanzas.registrar_pago', 'finanzas.reportes_fiscales']);

        // Rol Administrador tiene todos los permisos
        $rolAdmin = Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        $rolAdmin->givePermissionTo($permisos);

        // 4. Crear o actualizar usuarios de prueba
        $usuariosPrueba = [
            [
                'name' => 'Carlos Vendedor',
                'email' => 'vendedor@canocomputadoras.com',
                'role' => 'vendedor',
            ],
            [
                'name' => 'Supervisor de Ventas',
                'email' => 'supervisor.ventas@canocomputadoras.com',
                'role' => 'supervisor_ventas',
            ],
            [
                'name' => 'Luis Comprador',
                'email' => 'compras@canocomputadoras.com',
                'role' => 'comprador',
            ],
            [
                'name' => 'Gerente de Compras',
                'email' => 'gerente.compras@canocomputadoras.com',
                'role' => 'gerente_compras',
            ],
            [
                'name' => 'Ana Finanzas',
                'email' => 'auxiliar.finanzas@canocomputadoras.com',
                'role' => 'auxiliar_finanzas',
            ],
            [
                'name' => 'Gerente de Finanzas',
                'email' => 'gerente.finanzas@canocomputadoras.com',
                'role' => 'gerente_finanzas',
            ],
        ];

        foreach ($usuariosPrueba as $datos) {
            /** @var User $usuario */
            $usuario = User::firstOrCreate(
                ['email' => $datos['email']],
                [
                    'name' => $datos['name'],
                    'password' => Hash::make('password123'),
                    'role' => $datos['role'],
                    'es_propietario' => false,
                    'email_verified_at' => now(),
                ]
            );

            // Asegurar que el campo role coincida si ya existía
            if ($usuario->role !== $datos['role']) {
                $usuario->update(['role' => $datos['role']]);
            }

            // Sincronizar el rol en spatie/laravel-permission
            $usuario->syncRoles([$datos['role']]);
        }
    }
}
