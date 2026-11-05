<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class PersonalSucursalesSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::query()->first();
        if (! $empresa) {
            return;
        }

        $sucursalMatriz = Sucursal::query()->where('codigo', 'MATRIZ')->firstOrFail();
        $sucursalNorte = Sucursal::query()->where('codigo', 'NORTE')->firstOrFail();

        // Asegurar permisos requeridos de inventario
        Permission::findOrCreate('inventario.transferir', 'web');
        Permission::findOrCreate('inventario.registrar_entrada', 'web');

        $passwordDefault = Hash::make('password');

        // =========================================================================
        // 1. SUCURSAL MATRIZ (ID: 1)
        // =========================================================================

        // Administrador Matriz (Usamos al administrador secundario ID: 3 o lo creamos)
        $adminMatriz = User::query()->firstOrCreate(
            ['email' => 'admin.matriz@cano.test'],
            [
                'name' => 'Administrador Matriz',
                'password' => $passwordDefault,
                'email_verified_at' => now(),
            ]
        );
        $adminMatriz->syncRoles(['administrador']);
        $adminMatriz->givePermissionTo(['inventario.transferir', 'inventario.registrar_entrada']);
        $adminMatriz->sucursales()->sync([
            $sucursalMatriz->id => [
                'es_principal' => true,
                'es_gerente' => true,
                'activo' => true,
            ],
        ]);

        // Supervisor Matriz
        $supervisorMatriz = User::query()->firstOrCreate(
            ['email' => 'supervisor.matriz@cano.test'],
            [
                'name' => 'Supervisor Matriz',
                'password' => $passwordDefault,
                'email_verified_at' => now(),
            ]
        );
        $supervisorMatriz->syncRoles(['supervisor']);
        $supervisorMatriz->givePermissionTo(['inventario.transferir', 'inventario.registrar_entrada']);
        $supervisorMatriz->sucursales()->sync([
            $sucursalMatriz->id => [
                'es_principal' => true,
                'es_gerente' => false,
                'activo' => true,
            ],
        ]);

        // Empleado Matriz
        $empleadoMatriz = User::query()->firstOrCreate(
            ['email' => 'empleado.matriz@cano.test'],
            [
                'name' => 'Empleado Matriz',
                'password' => $passwordDefault,
                'email_verified_at' => now(),
            ]
        );
        $empleadoMatriz->syncRoles(['empleado']);
        $empleadoMatriz->sucursales()->sync([
            $sucursalMatriz->id => [
                'es_principal' => true,
                'es_gerente' => false,
                'activo' => true,
            ],
        ]);

        // =========================================================================
        // 2. SUCURSAL NORTE (ID: 2)
        // =========================================================================

        // Administrador Norte
        $adminNorte = User::query()->firstOrCreate(
            ['email' => 'admin.norte@cano.test'],
            [
                'name' => 'Administrador Norte',
                'password' => $passwordDefault,
                'email_verified_at' => now(),
            ]
        );
        $adminNorte->syncRoles(['administrador']);
        $adminNorte->givePermissionTo(['inventario.transferir', 'inventario.registrar_entrada']);
        $adminNorte->sucursales()->sync([
            $sucursalNorte->id => [
                'es_principal' => true,
                'es_gerente' => true,
                'activo' => true,
            ],
        ]);

        // Supervisor Norte (Alineamos al usuario ID: 4 actual)
        $supervisorNorte = User::query()->where('email', 'supervisor@cano.test')->first()
            ?? User::query()->firstOrCreate(
                ['email' => 'supervisor.norte@cano.test'],
                ['name' => 'Supervisor Norte', 'password' => $passwordDefault, 'email_verified_at' => now()]
            );
        $supervisorNorte->syncRoles(['supervisor']);
        $supervisorNorte->givePermissionTo(['inventario.transferir', 'inventario.registrar_entrada']);
        $supervisorNorte->sucursales()->sync([
            $sucursalNorte->id => [
                'es_principal' => true,
                'es_gerente' => false,
                'activo' => true,
            ],
        ]);

        // Empleado Norte (Alineamos al usuario ID: 5 actual)
        $empleadoNorte = User::query()->where('email', 'empleado@cano.test')->first()
            ?? User::query()->firstOrCreate(
                ['email' => 'empleado.norte@cano.test'],
                ['name' => 'Empleado Norte', 'password' => $passwordDefault, 'email_verified_at' => now()]
            );
        $empleadoNorte->syncRoles(['empleado']);
        $empleadoNorte->sucursales()->sync([
            $sucursalNorte->id => [
                'es_principal' => true,
                'es_gerente' => false,
                'activo' => true,
            ],
        ]);
    }
}
