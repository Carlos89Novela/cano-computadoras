<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo str_repeat('-', 110).PHP_EOL;
echo sprintf('%-4s | %-16s | %-25s | %-35s | %-15s', 'ID', 'N.EMPLEADO', 'ROL', 'NOMBRE', 'CORREO', 'CLAVE PROBABLE').PHP_EOL;
echo str_repeat('-', 110).PHP_EOL;

User::all()->each(function ($u) {
    $clave = 'Personalizada';
    if (Hash::check('password', $u->password)) {
        $clave = 'password';
    } elseif (Hash::check('password123', $u->password)) {
        $clave = 'password123';
    }

    echo sprintf(
        '%-4d | %-16s | %-25s | %-35s | %-15s',
        $u->id,
        $u->numero_empleado ?? 'N/A',
        $u->role ?? 'N/A',
        mb_strimwidth($u->name, 0, 25, '...'),
        mb_strimwidth($u->email, 0, 35, '...'),
        $clave
    ).PHP_EOL;
});

echo str_repeat('-', 110).PHP_EOL;
