<?php

use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------
// 1. CONTROLADORES GENERALES Y PÚBLICOS
// ---------------------------------------------------------
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\SeguimientoController;
use App\Http\Controllers\Public\AutofacturaController;
use App\Http\Controllers\Public\PublicCotizacionController;

// ---------------------------------------------------------
// 2. CONTROLADORES DEL TALLER Y ADMINISTRACIÓN
// ---------------------------------------------------------
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\OrdenServicioController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrdenServicioController as AdminOrdenServicioController;
use App\Http\Controllers\Admin\ServicioController as AdminServicioController;
use App\Http\Controllers\Admin\SucursalController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Admin\Inventario\InventarioOrdenController;
use App\Http\Controllers\Marketing\PromocionController;
use App\Http\Controllers\Supervisor\DashboardController as SupervisorDashboardController;
use App\Http\Controllers\Supervisor\AsignacionController;
use App\Http\Controllers\Supervisor\RevisionCotizacionController;
use App\Http\Controllers\Supervisor\EntregaOrdenController;
use App\Http\Controllers\Empleado\DashboardController as EmpleadoDashboardController;
use App\Http\Controllers\Empleado\OrdenAsignadaController;
use App\Http\Middleware\PropietarioMiddleware;
use App\Models\Servicio;

// ---------------------------------------------------------
// 3. CONTROLADORES DE OPERACIÓN, INVENTARIO Y FINANZAS
// ---------------------------------------------------------
use App\Http\Controllers\Productos\ProductoController;
use App\Http\Controllers\Productos\CategoriaProductoController;
use App\Http\Controllers\Productos\RecetaController;
use App\Http\Controllers\Proveedores\ProveedorController;
use App\Http\Controllers\Compras\OrdenCompraController;
use App\Http\Controllers\Compras\CuentaPorPagarController;
use App\Http\Controllers\Inventario\EntradaInventarioController;
use App\Http\Controllers\Inventario\RecepcionInventarioController;
use App\Http\Controllers\Inventario\TransferenciaInventarioController;
use App\Http\Controllers\Inventario\InventarioKardexController;
use App\Http\Controllers\Finanzas\BancoController;

// ---------------------------------------------------------
// 4. CONTROLADORES DE PUNTO DE VENTA (POS) Y CFDI
// ---------------------------------------------------------
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Pos\CajaAuditoriaController;
use App\Http\Controllers\Pos\FacturacionController;
use App\Http\Controllers\Pos\FacturacionGlobalController;

// ---------------------------------------------------------
// 5. CONTROLADORES EXCLUSIVOS DE RESTAURANTE
// ---------------------------------------------------------
use App\Http\Controllers\Restaurante\ComanderoController;
use App\Http\Controllers\Restaurante\KdsController;
use App\Http\Controllers\Restaurante\PlanoMesasController;
use App\Http\Controllers\Restaurante\CuentaMesaController;
use App\Http\Controllers\Restaurante\RecetarioController;
use App\Http\Controllers\Restaurante\ReservacionController;
use App\Http\Controllers\Restaurante\ReporteRestauranteController;


/*
|--------------------------------------------------------------------------
| BLOQUE 1: RUTAS PÚBLICAS Y DE CLIENTES EXTERNOS
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    $servicios = Servicio::query()
        ->where('activo', true)
        ->orderBy('nombre')
        ->get();

    return view('welcome', compact('servicios'));
});

// Seguimiento público de reparaciones mediante token
Route::get('/seguimiento/{token}', [SeguimientoController::class, 'show'])
    ->where('token', '[0-9A-HJKMNP-TV-Z]{26}')
    ->middleware('throttle:30,1')
    ->name('seguimiento.show');

// Portal público de autofacturación mediante QR de ticket
Route::prefix('autofactura')->name('autofactura.')->group(function (): void {
    Route::get('/{token}', [AutofacturaController::class, 'show'])->name('show');
    Route::post('/{token}/timbrar', [AutofacturaController::class, 'procesar'])->name('procesar');
});

// Portal público de cotizaciones con firma electrónica
Route::prefix('cotizacion')->name('public.cotizacion.')->group(function (): void {
    Route::get('/{token}', [PublicCotizacionController::class, 'show'])->name('show');
    Route::post('/{token}/autorizar', [PublicCotizacionController::class, 'autorizar'])->name('autorizar');
    Route::post('/{token}/rechazar', [PublicCotizacionController::class, 'rechazar'])->name('rechazar');
});


/*
|--------------------------------------------------------------------------
| BLOQUE 2: AUTENTICACIÓN BASE, PERFIL Y NOTIFICACIONES
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Notificaciones internas
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::post('/notificaciones/{notificacion}/leer', [NotificacionController::class, 'leer'])->name('notificaciones.leer');
    Route::post('/notificaciones/leer-todas', [NotificacionController::class, 'leerTodas'])->name('notificaciones.leer-todas');
});


/*
|--------------------------------------------------------------------------
| BLOQUE 3: TALLER DE REPARACIONES Y ADMINISTRACIÓN
|--------------------------------------------------------------------------
*/

// --- Clientes del Taller ---
Route::middleware('auth')->group(function (): void {
    Route::resource('equipos', EquipoController::class)->except(['show']);
    Route::resource('ordenes', OrdenServicioController::class)
        ->parameters(['ordenes' => 'orden'])
        ->only(['index', 'create', 'store', 'show']);
    Route::post('/ordenes/{orden}/autorizar', [OrdenServicioController::class, 'autorizar'])->name('ordenes.autorizar');
    Route::get('/ordenes/{orden}/pdf', [OrdenServicioController::class, 'pdf'])->name('ordenes.pdf');
});

// --- Técnicos y Empleados ---
Route::middleware(['auth', 'verified', 'role:empleado'])
    ->prefix('empleado')
    ->name('empleado.')
    ->group(function (): void {
        Route::get('/', [EmpleadoDashboardController::class, 'index'])->name('dashboard');
        Route::get('/ordenes/data', [OrdenAsignadaController::class, 'data'])->name('ordenes.data');
        Route::get('/ordenes/{orden}', [OrdenAsignadaController::class, 'show'])->name('ordenes.show');
        Route::patch('/ordenes/{orden}/tecnica', [OrdenAsignadaController::class, 'updateTechnical'])->name('ordenes.tecnica.update');
        Route::post('/ordenes/{orden}/iniciar', [OrdenAsignadaController::class, 'startRepair'])->name('ordenes.iniciar');
        Route::post('/ordenes/{orden}/pruebas', [OrdenAsignadaController::class, 'sendToTesting'])->name('ordenes.pruebas');
        Route::post('/ordenes/{orden}/lista-para-entrega', [OrdenAsignadaController::class, 'markReadyForDelivery'])->name('ordenes.lista-para-entrega');
        Route::post('/ordenes/{orden}/cotizacion/revision', [OrdenAsignadaController::class, 'requestQuoteReview'])->name('ordenes.cotizacion.revision');
    });

// --- Supervisión de Taller y Operaciones ---
Route::middleware(['auth', 'verified', 'role:supervisor'])
    ->prefix('supervisor')
    ->name('supervisor.')
    ->group(function (): void {
        Route::get('/', [SupervisorDashboardController::class, 'index'])->name('dashboard');
        Route::get('/cotizaciones/data', [RevisionCotizacionController::class, 'data'])->name('cotizaciones.data');
        Route::get('/cotizaciones/{orden}', [RevisionCotizacionController::class, 'show'])->name('cotizaciones.show');
    });

Route::middleware(['auth', 'verified', 'role:administrador|supervisor'])
    ->prefix('operacion')
    ->name('operacion.')
    ->group(function (): void {
        Route::post('/ordenes/{orden}/asignar', [AsignacionController::class, 'store'])->name('ordenes.asignar');
        Route::post('/ordenes/{orden}/cotizacion/aprobar', [RevisionCotizacionController::class, 'approve'])->name('cotizaciones.aprobar');
        Route::post('/ordenes/{orden}/cotizacion/rechazar', [RevisionCotizacionController::class, 'reject'])->name('cotizaciones.rechazar');
        Route::post('/ordenes/{orden}/entregar', [EntregaOrdenController::class, 'store'])->name('ordenes.entregar');
    });

// --- Panel Administrativo Global ---
Route::middleware(['auth', 'administrador'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Gestión de Órdenes
        Route::get('/ordenes', [AdminOrdenServicioController::class, 'index'])->name('ordenes.index');
        Route::get('/ordenes/data', [AdminOrdenServicioController::class, 'data'])->name('ordenes.data');
        Route::get('/ordenes/{orden}/editar', [AdminOrdenServicioController::class, 'edit'])->name('ordenes.edit');
        Route::put('/ordenes/{orden}', [AdminOrdenServicioController::class, 'update'])->name('ordenes.update');
        Route::get('/ordenes/exportar/csv', [AdminOrdenServicioController::class, 'exportCsv'])->name('ordenes.exportar.csv');
        Route::get('/ordenes/exportar/pdf', [AdminOrdenServicioController::class, 'exportPdf'])->name('ordenes.exportar.pdf');
        Route::get('/ordenes/{orden}/pdf', [AdminOrdenServicioController::class, 'pdf'])->name('ordenes.pdf');
        Route::post('/ordenes/bulk-update', [AdminOrdenServicioController::class, 'bulkUpdate'])->name('ordenes.bulk_update');

        // Refacciones y reservas en orden
        Route::post('/empresas/{empresa}/ordenes/{orden}/inventario/reservar', [InventarioOrdenController::class, 'reservar'])
            ->middleware('permission:inventario.consumir')->name('ordenes.inventario.reservar');
        Route::post('/empresas/{empresa}/ordenes/{orden}/inventario/consumir', [InventarioOrdenController::class, 'consumir'])
            ->middleware('permission:inventario.consumir')->name('ordenes.inventario.consumir');
        Route::post('/empresas/{empresa}/ordenes/{orden}/inventario/liberar', [InventarioOrdenController::class, 'liberar'])
            ->middleware('permission:inventario.consumir')->name('ordenes.inventario.liberar');

        // Servicios y Tarifas
        Route::resource('servicios', AdminServicioController::class)->parameters(['servicios' => 'servicio'])->except(['show']);

        // Configuración Propietario (Usuarios, Permisos, Sucursales)
        Route::middleware(PropietarioMiddleware::class)->group(function (): void {
            Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
            Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
            Route::patch('/usuarios/{usuario}/rol', [UsuarioController::class, 'updateRole'])->name('usuarios.rol.update');
            Route::patch('/usuarios/{usuario}/permisos', [UsuarioController::class, 'updatePermissions'])->name('usuarios.permisos.update');

            Route::get('/empresas/{empresa}/sucursales', [SucursalController::class, 'index'])->name('empresas.sucursales.index');
            Route::get('/empresas/{empresa}/sucursales/crear', [SucursalController::class, 'create'])->name('empresas.sucursales.create');
            Route::post('/empresas/{empresa}/sucursales', [SucursalController::class, 'store'])->name('empresas.sucursales.store');
        });
    });


/*
|--------------------------------------------------------------------------
| BLOQUE 4: OPERACIONES GENERALES (CATÁLOGO, INVENTARIO, COMPRAS, BANCOS)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function (): void {

    // --- Catálogo de Insumos y Categorías ---
    Route::prefix('operacion')->name('productos.')->group(function (): void {
        Route::get('/empresas/{empresa}/productos', [ProductoController::class, 'index'])->middleware('permission:productos.ver')->name('index');
        Route::get('/empresas/{empresa}/productos/data', [ProductoController::class, 'data'])->middleware('permission:productos.ver')->name('data');
        Route::get('/empresas/{empresa}/productos/crear', [ProductoController::class, 'create'])->middleware('permission:productos.crear')->name('create');
        Route::post('/empresas/{empresa}/productos', [ProductoController::class, 'store'])->middleware('permission:productos.crear')->name('store');

        Route::get('/empresas/{empresa}/categorias-producto', [CategoriaProductoController::class, 'index'])->middleware('permission:productos.ver')->name('categorias.index');
        Route::get('/empresas/{empresa}/categorias-producto/crear', [CategoriaProductoController::class, 'create'])->middleware('permission:productos.crear')->name('categorias.create');
        Route::post('/empresas/{empresa}/categorias-producto', [CategoriaProductoController::class, 'store'])->middleware('permission:productos.crear')->name('categorias.store');
        Route::get('/empresas/{empresa}/categorias-producto/{categoria}/editar', [CategoriaProductoController::class, 'edit'])->middleware('permission:productos.actualizar')->name('categorias.edit');
        Route::put('/empresas/{empresa}/categorias-producto/{categoria}', [CategoriaProductoController::class, 'update'])->middleware('permission:productos.actualizar')->name('categorias.update');
        Route::patch('/empresas/{empresa}/categorias-producto/{categoria}/estado', [CategoriaProductoController::class, 'updateStatus'])->middleware('permission:productos.cambiar_estado')->name('categorias.estado.update');
    });

    // --- Proveedores ---
    Route::prefix('operacion')->name('proveedores.')->group(function (): void {
        Route::get('/empresas/{empresa}/proveedores', [ProveedorController::class, 'index'])->middleware('permission:productos.ver')->name('index');
        Route::get('/empresas/{empresa}/proveedores/crear', [ProveedorController::class, 'create'])->middleware('permission:productos.crear')->name('create');
        Route::post('/empresas/{empresa}/proveedores', [ProveedorController::class, 'store'])->middleware('permission:productos.crear')->name('store');
        Route::get('/empresas/{empresa}/proveedores/{proveedor}/editar', [ProveedorController::class, 'edit'])->middleware('permission:productos.actualizar')->name('edit');
        Route::put('/empresas/{empresa}/proveedores/{proveedor}', [ProveedorController::class, 'update'])->middleware('permission:productos.actualizar')->name('update');
        Route::patch('/empresas/{empresa}/proveedores/{proveedor}/estado', [ProveedorController::class, 'updateStatus'])->middleware('permission:productos.cambiar_estado')->name('estado.update');
    });

    // --- Órdenes de Compra y Cuentas por Pagar (CXP) ---
    Route::prefix('operacion/compras')->group(function (): void {
        Route::name('compras.ordenes.')->group(function (): void {
            Route::get('/', [OrdenCompraController::class, 'index'])->name('index');
            Route::get('/crear', [OrdenCompraController::class, 'create'])->name('create');
            Route::post('/', [OrdenCompraController::class, 'store'])->name('store');
            Route::get('/{orden}', [OrdenCompraController::class, 'show'])->name('show');
            Route::get('/{orden}/pdf', [OrdenCompraController::class, 'pdf'])->name('pdf');
            Route::patch('/{orden}/solicitar', [OrdenCompraController::class, 'solicitar'])->name('solicitar');
            Route::patch('/{orden}/autorizar', [OrdenCompraController::class, 'autorizar'])->middleware('permission:compras.autorizar')->name('autorizar');
            Route::patch('/{orden}/cancelar', [OrdenCompraController::class, 'cancelar'])->name('cancelar');
        });

        Route::prefix('cuentas-por-pagar')->name('compras.cxp.')->group(function (): void {
            Route::get('/', [CuentaPorPagarController::class, 'index'])->name('index');
            Route::post('/subir-xml', [CuentaPorPagarController::class, 'subirXml'])->name('subir-xml');
            Route::post('/{cuenta}/pagar', [CuentaPorPagarController::class, 'registrarPago'])->name('pagar');
        });
    });

    // --- Inventarios, Entradas, Recepciones, Transferencias y Kardex ---
    Route::prefix('operacion')->name('inventario.')->group(function (): void {
        // Entradas simples
        Route::get('/empresas/{empresa}/inventario/entradas/crear', [EntradaInventarioController::class, 'create'])->middleware('permission:inventario.registrar_entrada')->name('entradas.create');
        Route::post('/empresas/{empresa}/inventario/entradas', [EntradaInventarioController::class, 'store'])->middleware('permission:inventario.registrar_entrada')->name('entradas.store');

        // Recepciones con orden de compra
        Route::get('/empresas/{empresa}/inventario/recepciones', [RecepcionInventarioController::class, 'index'])->middleware('permission:inventario.registrar_entrada')->name('recepciones.index');
        Route::get('/empresas/{empresa}/inventario/recepciones/data', [RecepcionInventarioController::class, 'data'])->middleware('permission:inventario.registrar_entrada')->name('recepciones.data');
        Route::get('/empresas/{empresa}/inventario/recepciones/crear', [RecepcionInventarioController::class, 'create'])->middleware('permission:inventario.registrar_entrada')->name('recepciones.create');
        Route::post('/empresas/{empresa}/inventario/recepciones', [RecepcionInventarioController::class, 'store'])->middleware('permission:inventario.registrar_entrada')->name('recepciones.store');
        Route::get('/empresas/{empresa}/inventario/ordenes-compra/{orden}/detalles', [RecepcionInventarioController::class, 'ordenCompraData'])->name('recepciones.orden-data');

        // Transferencias entre almacenes (3 fases)
        Route::prefix('empresas/{empresa}/inventario/transferencias')->name('transferencias.')->group(function (): void {
            Route::get('/', [TransferenciaInventarioController::class, 'index'])->name('index');
            Route::get('/data', [TransferenciaInventarioController::class, 'data'])->name('data');
            Route::get('/solicitar', [TransferenciaInventarioController::class, 'create'])->name('create');
            Route::post('/', [TransferenciaInventarioController::class, 'store'])->name('store');
            Route::get('/{transferencia}/despachar', [TransferenciaInventarioController::class, 'despacharView'])->name('despachar.view');
            Route::post('/{transferencia}/despachar', [TransferenciaInventarioController::class, 'despacharStore'])->name('despachar.store');
            Route::get('/{transferencia}/recibir', [TransferenciaInventarioController::class, 'recibirView'])->name('recibir.view');
            Route::post('/{transferencia}/recibir', [TransferenciaInventarioController::class, 'recibirStore'])->name('recibir.store');
        });

        // Existencias y Kardex
        Route::get('/empresas/{empresa}/inventario/existencias', [InventarioKardexController::class, 'index'])->name('existencias.index');
        Route::get('/empresas/{empresa}/inventario/existencias/data', [InventarioKardexController::class, 'dataExistencias'])->name('existencias.data');
        Route::get('/empresas/{empresa}/inventario/kardex/{producto}/{almacen?}', [InventarioKardexController::class, 'kardex'])->name('kardex.show');
        Route::get('/empresas/{empresa}/inventario/kardex-data/{producto}/{almacen?}', [InventarioKardexController::class, 'dataKardex'])->name('kardex.data');
        Route::get('/empresas/{empresa}/inventario/kardex-pdf/{producto}/{almacen?}', [InventarioKardexController::class, 'exportarKardexPdf'])->name('kardex.pdf');
    });

    // --- Bancos y Tesorería ---
    Route::prefix('operacion/empresas/{empresa}/bancos')->name('operacion.bancos.')->group(function (): void {
        Route::get('/', [BancoController::class, 'index'])->name('index');
        Route::post('/movimientos', [BancoController::class, 'storeMovimiento'])->name('movimientos.store');
        Route::post('/movimientos/{movimiento}/conciliar', [BancoController::class, 'conciliar'])->name('movimientos.conciliar');
    });
});


/*
|--------------------------------------------------------------------------
| BLOQUE 5: PUNTO DE VENTA (POS) Y FACTURACIÓN ELECTRÓNICA CFDI
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function (): void {
    // Terminal POS
    Route::prefix('pos')->name('pos.')->group(function (): void {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::get('/catalogo', [PosController::class, 'catalogo'])->name('catalogo');
        Route::post('/caja/apertura', [PosController::class, 'aperturaCaja'])->name('caja.apertura');
        Route::post('/caja/cierre', [PosController::class, 'cierreCaja'])->name('caja.cierre');
        Route::post('/ventas', [PosController::class, 'storeVenta'])->name('ventas.store');
        Route::post('/sincronizar-offline', [PosController::class, 'sincronizarOffline'])->name('sincronizar.offline');
        Route::get('/ticket/{venta}', [PosController::class, 'ticket'])->name('ticket');
        Route::get('/ultimas-ventas', [PosController::class, 'ultimasVentas'])->name('ultimas-ventas');
        Route::get('/ventas/{uuid}/ticket', [PosController::class, 'reimprimirTicket'])->name('ventas.ticket');
        Route::post('/devoluciones', [PosController::class, 'devolucion'])->middleware('permission:pos.devoluciones')->name('devoluciones.store');
       // Cuentas y Mesas de Restaurante
        Route::get('/restaurante/comandas-abiertas', [PosController::class, 'comandasAbiertasRestaurante'])->name('restaurante.comandas-abiertas');
        Route::get('/restaurante/mesa/{mesa}/imprimir-precuenta', [PosController::class, 'imprimirPrecuentaMesa'])->name('restaurante.mesa.precuenta');
        //Corte de Caja
        Route::get('/caja/{turno}/resumen-parcial', [PosController::class, 'resumenCorteParcial'])->name('caja.resumen-parcial');
        Route::post('/caja/movimiento', [PosController::class, 'registrarMovimientoCaja'])->name('caja.movimiento');
        Route::get('/caja/{turno}/imprimir-corte', [PosController::class, 'imprimirTicketCorte'])->name('caja.imprimir-corte');
        
    });

    // Auditoría de turnos y cortes
    Route::prefix('operacion/cajas')->name('pos.cajas.')->group(function (): void {
        Route::get('/', [CajaAuditoriaController::class, 'index'])->name('index');
        Route::get('/{turno}', [CajaAuditoriaController::class, 'show'])->name('show');
        Route::post('/{turno}/movimiento', [CajaAuditoriaController::class, 'registrarMovimiento'])->name('movimiento');
        Route::get('/{turno}/corte-pdf', [CajaAuditoriaController::class, 'cortePdf'])->name('corte-pdf');
    });

    // Facturación SAT CFDI 4.0
    Route::prefix('operacion/facturacion')->name('facturacion.')->group(function (): void {
        Route::post('/ventas/{venta}/timbrar', [FacturacionController::class, 'timbrar'])->name('ventas.timbrar');
        Route::get('/ventas/{venta}/descargar-xml', [FacturacionController::class, 'descargarXml'])->name('ventas.descargar-xml');
        Route::get('/ventas/{venta}/descargar-pdf', [FacturacionController::class, 'descargarPdf'])->name('ventas.descargar-pdf');
        Route::post('/lote-pendientes', [FacturacionController::class, 'procesarLotePendientes'])->name('lote-pendientes');
        Route::get('/global', [FacturacionGlobalController::class, 'index'])->name('global.index');
        Route::post('/global/procesar', [FacturacionGlobalController::class, 'procesar'])->name('global.procesar');
    });

    // Rutas POS para Cuentas de Restaurante
    Route::get('/restaurante/comandas-abiertas', [App\Http\Controllers\Pos\PosController::class, 'comandasAbiertasRestaurante'])
        ->name('restaurante.comandas-abiertas');
    Route::get('/restaurante/comanda/{comanda}/imprimir-precuenta', [App\Http\Controllers\Pos\PosController::class, 'imprimirPrecuentaComanda'])
        ->name('restaurante.comanda.precuenta');

});


/*
|--------------------------------------------------------------------------
| BLOQUE 6: MÓDULO EXCLUSIVO DE RESTAURANTE (LA FOGATA BISTRO)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->prefix('restaurante')->group(function (): void {

    // --- 1. Comandero Móvil para Meseros ---
    Route::prefix('comandero')->name('restaurante.comandero.')->group(function (): void {
        Route::get('/', [ComanderoController::class, 'index'])->name('index');
        Route::get('/catalogo', [ComanderoController::class, 'catalogo'])->name('catalogo');
        Route::post('/enviar', [ComanderoController::class, 'enviarComanda'])->name('enviar');
        Route::get('/alertas-listas', [ComanderoController::class, 'alertasListas'])->name('alertas-listas');
        Route::post('/pedir-cuenta-mesa', [App\Http\Controllers\Restaurante\ComanderoController::class, 'pedirCuentaMesa'])->name('pedir-cuenta-mesa');
        Route::get('/estado-mesas', [ComanderoController::class, 'estadoMesas'])->name('estado-mesas');
    });

    // --- 2. Monitor KDS de Cocina y Barra ---
    Route::prefix('kds')->name('restaurante.kds.')->group(function (): void {
        Route::get('/', [KdsController::class, 'index'])->name('index');
        Route::get('/comandas-activas', [KdsController::class, 'comandasActivas'])->name('comandas-activas');
        Route::patch('/items/{item}/estado', [KdsController::class, 'cambiarEstadoItem'])->name('items.estado');
        Route::post('/comandas/{comanda}/completar', [KdsController::class, 'completarComanda'])->name('completar');
    });

    // --- 3. Plano Gráfico de Mesas (Floor Plan) ---
    Route::prefix('mesas')->name('restaurante.mesas.')->group(function (): void {
        Route::get('/plano', [PlanoMesasController::class, 'index'])->name('plano');
        Route::post('/guardar-posiciones', [PlanoMesasController::class, 'guardarPosiciones'])->middleware('role:administrador|supervisor')->name('guardar-posiciones');
        Route::post('/crear', [PlanoMesasController::class, 'storeMesa'])->middleware('role:administrador|supervisor')->name('store');

        // Consumo activo de mesa (consultado en vivo por el comandero)
        Route::get('/{mesa}/consumo-activo', [ComanderoController::class, 'consumoActivoMesa'])->name('consumo-activo');

        // Precuenta, cobro y división de cuenta
        Route::get('/{mesa}/precuenta', [CuentaMesaController::class, 'precuenta'])->name('precuenta');
        Route::patch('/{mesa}/pedir-cuenta', [CuentaMesaController::class, 'pedirCuenta'])->name('pedir-cuenta');
        Route::post('/{mesa}/liquidar', [CuentaMesaController::class, 'liquidarMesa'])->name('liquidar');
        Route::post('/{mesa}/dividir-cuenta', [CuentaMesaController::class, 'dividirCuenta'])->name('dividir-cuenta');

        // Edición y Eliminación de Mesas en el Plano
        Route::put('/{mesa}', [PlanoMesasController::class, 'updateMesa'])->middleware('role:administrador|supervisor')->name('update');
        Route::delete('/{mesa}', [PlanoMesasController::class, 'destroyMesa'])->middleware('role:administrador|supervisor')->name('destroy');
    });

    // --- 4. Recetario Exclusivo y Fichas Técnicas (BOM) ---
    Route::prefix('recetas')->name('restaurante.recetas.')->group(function (): void {
        Route::get('/', [RecetarioController::class, 'index'])->name('index');
        Route::get('/data', [RecetarioController::class, 'data'])->name('data');
    });

    // --- 5. Reservaciones de Mesas ---
    Route::prefix('reservaciones')->name('restaurante.reservaciones.')->group(function (): void {
        Route::get('/', [ReservacionController::class, 'index'])->name('index');
        Route::post('/', [ReservacionController::class, 'store'])->name('store');
        Route::patch('/{reservacion}/sentar', [ReservacionController::class, 'sentar'])->name('sentar');
        Route::patch('/{reservacion}/estado', [ReservacionController::class, 'updateEstado'])->name('estado.update');
    });

    // --- 6. Reportes y Analítica ---
    Route::prefix('reportes')->name('restaurante.reportes.')->middleware('role:administrador|supervisor')->group(function (): void {
        Route::get('/', [ReporteRestauranteController::class, 'index'])->name('index');
    });
});

// =========================================================
// BLOQUE 7 PROMOCIONES, COMBOS Y PROGRAMA DE LEALTAD / MONEDERO
// =========================================================
Route::middleware(['auth', 'verified'])
    ->prefix('marketing/promociones')
    ->name('marketing.promociones.')
    ->group(function (): void {
        Route::get('/', [PromocionController::class, 'index'])->name('index');
        Route::post('/', [PromocionController::class, 'store'])->name('store');
        Route::patch('/{promocion}/toggle', [PromocionController::class, 'toggleEstado'])->name('toggle');
        Route::delete('/{promocion}', [PromocionController::class, 'destroy'])->name('destroy');
        Route::put('{promocion}', [\App\Http\Controllers\Marketing\PromocionController::class, 'update'])->name('update');
        Route::get('/{promocion}', fn () => redirect()->route('marketing.promociones.index'));
        Route::post('/clientes/{cliente}/lealtad', [PromocionController::class, 'actualizarLealtadCliente'])->name('clientes.lealtad');
        Route::get('/vigentes-api', [PromocionController::class, 'vigentesApi'])->name('vigentes');
        Route::post('/clientes', [PromocionController::class, 'storeCliente'])->name('clientes.store');
    });

// Ruta heredada para edición directa de receta técnica por producto
Route::middleware(['auth', 'verified'])
    ->prefix('operacion/empresas/{empresa}/productos/{producto}/receta')
    ->name('productos.recetas.')
    ->group(function (): void {
        Route::get('/', [RecetaController::class, 'edit'])->name('edit');
        Route::put('/', [RecetaController::class, 'update'])->name('update');
    });

require __DIR__.'/auth.php';
