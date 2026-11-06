<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

// Contraseña estándar temporal para pruebas
$passwordDefault = Hash::make('password123');

$mapeo = [
    1 => ['email' => 'test@canocomputadoras.com',                  'numero' => 'EMP-0001', 'role' => 'empleado'],
    2 => ['email' => 'administrador@canocomputadoras.com',         'numero' => 'ADM-0001', 'role' => 'administrador'],
    3 => ['email' => 'admin.secundario@canocomputadoras.com',      'numero' => 'ADM-0002', 'role' => 'administrador'],
    4 => ['email' => 'supervisor@canocomputadoras.com',            'numero' => 'SUP-0001', 'role' => 'supervisor'],
    5 => ['email' => 'empleado@canocomputadoras.com',              'numero' => 'EMP-0002', 'role' => 'empleado'],
    6 => ['email' => 'cliente1@canocomputadoras.com',              'numero' => 'CLI-0001', 'role' => 'cliente'],
    7 => ['email' => 'cliente2@canocomputadoras.com',              'numero' => 'CLI-0002', 'role' => 'cliente'],
    8 => ['email' => 'admin.matriz@canocomputadoras.com',          'numero' => 'ADM-0003', 'role' => 'administrador'],
    9 => ['email' => 'supervisor.matriz@canocomputadoras.com',     'numero' => 'SUP-0002', 'role' => 'supervisor'],
    10 => ['email' => 'empleado.matriz@canocomputadoras.com',       'numero' => 'EMP-0003', 'role' => 'empleado'],
    11 => ['email' => 'admin.norte@canocomputadoras.com',           'numero' => 'ADM-0004', 'role' => 'administrador'],
    12 => ['email' => 'vendedor@canocomputadoras.com',              'numero' => 'VTA-0001', 'role' => 'vendedor'],
    13 => ['email' => 'supervisor.ventas@canocomputadoras.com',     'numero' => 'SUP-0003', 'role' => 'supervisor_ventas'],
    14 => ['email' => 'compras@canocomputadoras.com',               'numero' => 'CMP-0001', 'role' => 'comprador'],
    15 => ['email' => 'gerente.compras@canocomputadoras.com',       'numero' => 'CMP-0002', 'role' => 'gerente_compras'],
    16 => ['email' => 'auxiliar.finanzas@canocomputadoras.com',     'numero' => 'FIN-0001', 'role' => 'auxiliar_finanzas'],
    17 => ['email' => 'gerente.finanzas@canocomputadoras.com',      'numero' => 'FIN-0002', 'role' => 'gerente_finanzas'],
];

echo "Iniciando homologación...\n";

foreach ($mapeo as $id => $datos) {
    /** @var User|null $usuario */
    $usuario = User::find($id);
    if (! $usuario) {
        continue;
    }

    $usuario->email = $datos['email'];
    $usuario->numero_empleado = $datos['numero'];
    $usuario->role = $datos['role'];
    $usuario->password = $passwordDefault; // Se establece 'password123' para todos
    $usuario->save();

    // Sincronizar también rol de Spatie si existe
    if (Role::where('name', $datos['role'])->exists()) {
        $usuario->syncRoles([$datos['role']]);
    }

    echo "OK -> ID {$id}: {$datos['numero']} | {$datos['email']} | Rol: {$datos['role']}\n";
}

echo "Proceso finalizado. Todas las contraseñas han sido restablecidas a: password123\n";
