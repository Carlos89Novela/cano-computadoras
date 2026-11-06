<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

$sucursal = Sucursal::query()->first();

if (! $sucursal) {
    echo "No se encontró ninguna sucursal registrada.\n";
    exit(1);
}

echo "Asignando personal a la sucursal: {$sucursal->nombre} (ID: {$sucursal->id})\n";

$correos = [
    'vendedor@canocomputadoras.com',
    'supervisor.ventas@canocomputadoras.com',
    'compras@canocomputadoras.com',
    'gerente.compras@canocomputadoras.com',
    'auxiliar.finanzas@canocomputadoras.com',
    'gerente.finanzas@canocomputadoras.com',
];

foreach ($correos as $email) {
    /** @var User|null $usuario */
    $usuario = User::query()->where('email', $email)->first();

    if (! $usuario) {
        echo "[-] Usuario no encontrado: {$email}\n";

        continue;
    }

    if (method_exists($usuario, 'sucursales')) {
        $yaAsignado = $usuario->sucursales()->where('sucursal_id', $sucursal->id)->exists();

        $esGerente = str_contains($email, 'supervisor') || str_contains($email, 'gerente');

        if (! $yaAsignado) {
            $usuario->sucursales()->attach($sucursal->id, [
                'es_gerente' => $esGerente,
            ]);
            echo "[+] Asignada sucursal a: {$usuario->name} (Gerente: ".($esGerente ? 'Sí' : 'No').")\n";
        } else {
            echo "[=] Ya estaba asignado: {$usuario->name}\n";
        }
    } else {
        echo "[!] El modelo User no tiene definida la relación 'sucursales'.\n";
    }
}

echo "Finalizado.\n";
