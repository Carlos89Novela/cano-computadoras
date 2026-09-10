<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrdenServicioController as AdminOrdenServicioController;
use App\Http\Controllers\Admin\ServicioController as AdminServicioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Empleado\DashboardController as EmpleadoDashboardController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\OrdenServicioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SeguimientoController;
use App\Http\Controllers\Supervisor\AsignacionController;
use App\Http\Controllers\Supervisor\DashboardController as SupervisorDashboardController;
use App\Models\Servicio;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $servicios = Servicio::query()
        ->where('activo', true)
        ->orderBy('nombre')
        ->get();

    return view('welcome', compact('servicios'));
});

Route::get('/seguimiento/{token}', [SeguimientoController::class, 'show'])
    ->where(
        'token',
        '[0-9A-HJKMNP-TV-Z]{26}'
    )
    ->middleware('throttle:30,1')
    ->name('seguimiento.show');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

    Route::resource('equipos', EquipoController::class)
        ->except(['show']);

    Route::resource('ordenes', OrdenServicioController::class)
        ->parameters(['ordenes' => 'orden'])
        ->only(['index', 'create', 'store', 'show']);

    Route::post(
        '/ordenes/{orden}/autorizar',
        [OrdenServicioController::class, 'autorizar']
    )->name('ordenes.autorizar');

    Route::get(
        '/ordenes/{orden}/pdf',
        [OrdenServicioController::class, 'pdf']
    )->name('ordenes.pdf');

    Route::get(
        '/notificaciones',
        [NotificacionController::class, 'index']
    )->name('notificaciones.index');

    Route::post(
        '/notificaciones/{notificacion}/leer',
        [NotificacionController::class, 'leer']
    )->name('notificaciones.leer');

    Route::post(
        '/notificaciones/leer-todas',
        [NotificacionController::class, 'leerTodas']
    )->name('notificaciones.leer-todas');
});

Route::middleware(['auth', 'administrador'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get(
            '/',
            [AdminDashboardController::class, 'index']
        )->name('dashboard');

        Route::get(
            '/ordenes',
            [AdminOrdenServicioController::class, 'index']
        )->name('ordenes.index');

        Route::get(
            '/ordenes/data',
            [AdminOrdenServicioController::class, 'data']
        )->name('ordenes.data');

        Route::get(
            '/ordenes/{orden}/editar',
            [AdminOrdenServicioController::class, 'edit']
        )->name('ordenes.edit');

        Route::put(
            '/ordenes/{orden}',
            [AdminOrdenServicioController::class, 'update']
        )->name('ordenes.update');

        Route::get(
            '/ordenes/exportar/csv',
            [AdminOrdenServicioController::class, 'exportCsv']
        )->name('ordenes.exportar.csv');

        Route::get(
            '/ordenes/exportar/pdf',
            [AdminOrdenServicioController::class, 'exportPdf']
        )->name('ordenes.exportar.pdf');

        Route::get(
            '/ordenes/{orden}/pdf',
            [AdminOrdenServicioController::class, 'pdf']
        )->name('ordenes.pdf');

        Route::post(
            '/ordenes/bulk-update',
            [AdminOrdenServicioController::class, 'bulkUpdate']
        )->name('ordenes.bulk_update');

        Route::resource('servicios', AdminServicioController::class)
            ->parameters(['servicios' => 'servicio'])
            ->except(['show']);

    });

Route::middleware([
    'auth',
    'verified',
    'role:supervisor',
])
    ->prefix('supervisor')
    ->name('supervisor.')
    ->group(function () {
        Route::get(
            '/',
            [SupervisorDashboardController::class, 'index']
        )->name('dashboard');
    });

Route::middleware([
    'auth',
    'verified',
    'role:empleado',
])
    ->prefix('empleado')
    ->name('empleado.')
    ->group(function () {
        Route::get(
            '/',
            [EmpleadoDashboardController::class, 'index']
        )->name('dashboard');
    });

Route::middleware([
    'auth',
    'verified',
    'role:administrador|supervisor',
])
    ->prefix('operacion')
    ->name('operacion.')
    ->group(function () {
        Route::post(
            '/ordenes/{orden}/asignar',
            [AsignacionController::class, 'store']
        )->name('ordenes.asignar');
    });

require __DIR__.'/auth.php';
