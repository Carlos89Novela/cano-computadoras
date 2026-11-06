<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Almacen;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

$user = User::where('email', 'vendedor@canocomputadoras.com')->first();

echo 'Usuario: '.($user ? $user->name." (ID: {$user->id})" : 'No encontrado').PHP_EOL;

$sucursal = $user->sucursales()->first() ?? Sucursal::first();
echo 'Sucursal encontrada: '.($sucursal ? "{$sucursal->nombre} (ID: {$sucursal->id})" : 'NINGUNA').PHP_EOL;

$almacen = Almacen::where('activo', true)->first();
echo 'Almacen encontrado: '.($almacen ? "{$almacen->nombre} (ID: {$almacen->id}, Sucursal ID: {$almacen->sucursal_id})" : 'NINGUNO').PHP_EOL;
