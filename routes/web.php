<?php

// ---------------------------------------------------------
// IMPORTACIONES DE CONTROLADORES
// Se agrupan los controladores de administración, compras,
// inventarios, pos, facturación, productos y recetas.
// ---------------------------------------------------------

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\Inventario\InventarioOrdenController;
use App\Http\Controllers\Admin\OrdenServicioController as AdminOrdenServicioController;
use App\Http\Controllers\Admin\ServicioController as AdminServicioController;
use App\Http\Controllers\Admin\SucursalController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Compras\OrdenCompraController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Empleado\DashboardController as EmpleadoDashboardController;
use App\Http\Controllers\Empleado\OrdenAsignadaController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\Inventario\EntradaInventarioController;
use App\Http\Controllers\Inventario\InventarioKardexController;
use App\Http\Controllers\Inventario\RecepcionInventarioController;
use App\Http\Controllers\Inventario\TransferenciaInventarioController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\OrdenServicioController;
use App\Http\Controllers\Pos\CajaAuditoriaController;
use App\Http\Controllers\Pos\FacturacionController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Productos\CategoriaProductoController;
use App\Http\Controllers\Productos\ProductoController;
use App\Http\Controllers\Productos\RecetaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Proveedores\ProveedorController;
use App\Http\Controllers\Public\AutofacturaController;
use App\Http\Controllers\Restaurante\ComanderoController;
use App\Http\Controllers\Restaurante\CuentaMesaController;
use App\Http\Controllers\Restaurante\KdsController;
use App\Http\Controllers\Restaurante\PlanoMesasController;
use App\Http\Controllers\SeguimientoController;
use App\Http\Controllers\Supervisor\AsignacionController;
use App\Http\Controllers\Supervisor\DashboardController as SupervisorDashboardController;
use App\Http\Controllers\Supervisor\EntregaOrdenController;
use App\Http\Controllers\Supervisor\RevisionCotizacionController;
use App\Http\Middleware\PropietarioMiddleware;
use App\Models\Servicio;
use Illuminate\Support\Facades\Route;

// =========================================================
// RUTAS PÚBLICAS Y DE SEGUIMIENTO EXTERNO
// =========================================================

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

// =========================================================
// PERFIL DE USUARIO Y NOTIFICACIONES
// =========================================================

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

// =========================================================
// PANEL DE ADMINISTRACIÓN GLOBAL
// =========================================================

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

        Route::post(
            '/empresas/{empresa}/ordenes/{orden}/inventario/reservar',
            [InventarioOrdenController::class, 'reservar']
        )
            ->middleware('permission:inventario.consumir')
            ->name('ordenes.inventario.reservar');

        Route::post(
            '/empresas/{empresa}/ordenes/{orden}/inventario/consumir',
            [InventarioOrdenController::class, 'consumir']
        )
            ->middleware('permission:inventario.consumir')
            ->name('ordenes.inventario.consumir');

        Route::post(
            '/empresas/{empresa}/ordenes/{orden}/inventario/liberar',
            [InventarioOrdenController::class, 'liberar']
        )
            ->middleware('permission:inventario.consumir')
            ->name('ordenes.inventario.liberar');

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

        Route::middleware(
            PropietarioMiddleware::class
        )->group(function (): void {
            Route::get(
                '/usuarios',
                [UsuarioController::class, 'index']
            )->name('usuarios.index');

            Route::get(
                '/usuarios/{usuario}/editar',
                [UsuarioController::class, 'edit']
            )->name('usuarios.edit');

            Route::patch(
                '/usuarios/{usuario}/rol',
                [UsuarioController::class, 'updateRole']
            )->name('usuarios.rol.update');

            Route::patch(
                '/usuarios/{usuario}/permisos',
                [UsuarioController::class, 'updatePermissions']
            )->name('usuarios.permisos.update');

            Route::get(
                '/empresas/{empresa}/sucursales',
                [SucursalController::class, 'index']
            )->name('empresas.sucursales.index');

            Route::get(
                '/empresas/{empresa}/sucursales/crear',
                [SucursalController::class, 'create']
            )->name('empresas.sucursales.create');

            Route::post(
                '/empresas/{empresa}/sucursales',
                [SucursalController::class, 'store']
            )->name('empresas.sucursales.store');
        });
    });

// =========================================================
// CATÁLOGO DE PRODUCTOS Y CATEGORÍAS
// =========================================================

Route::middleware([
    'auth',
    'verified',
])
    ->prefix('operacion')
    ->name('productos.')
    ->group(function (): void {
        Route::get(
            '/empresas/{empresa}/productos',
            [ProductoController::class, 'index']
        )
            ->middleware('permission:productos.ver')
            ->name('index');

        Route::get(
            '/empresas/{empresa}/productos/data',
            [ProductoController::class, 'data']
        )
            ->middleware('permission:productos.ver')
            ->name('data');

        Route::get(
            '/empresas/{empresa}/productos/crear',
            [ProductoController::class, 'create']
        )
            ->middleware('permission:productos.crear')
            ->name('create');

        Route::post(
            '/empresas/{empresa}/productos',
            [ProductoController::class, 'store']
        )
            ->middleware('permission:productos.crear')
            ->name('store');

        Route::get(
            '/empresas/{empresa}/categorias-producto',
            [CategoriaProductoController::class, 'index']
        )
            ->middleware('permission:productos.ver')
            ->name('categorias.index');

        Route::get(
            '/empresas/{empresa}/categorias-producto/crear',
            [CategoriaProductoController::class, 'create']
        )
            ->middleware('permission:productos.crear')
            ->name('categorias.create');

        Route::post(
            '/empresas/{empresa}/categorias-producto',
            [CategoriaProductoController::class, 'store']
        )
            ->middleware('permission:productos.crear')
            ->name('categorias.store');

        Route::get(
            '/empresas/{empresa}/categorias-producto/{categoria}/editar',
            [CategoriaProductoController::class, 'edit']
        )
            ->middleware('permission:productos.actualizar')
            ->name('categorias.edit');

        Route::put(
            '/empresas/{empresa}/categorias-producto/{categoria}',
            [CategoriaProductoController::class, 'update']
        )
            ->middleware('permission:productos.actualizar')
            ->name('categorias.update');

        Route::patch(
            '/empresas/{empresa}/categorias-producto/{categoria}/estado',
            [CategoriaProductoController::class, 'updateStatus']
        )
            ->middleware('permission:productos.cambiar_estado')
            ->name('categorias.estado.update');
    });

// =========================================================
// GESTIÓN DE RECETAS E INSUMOS (BOM / RESTAURANTES)
// =========================================================

Route::middleware(['auth', 'verified'])
    ->prefix('operacion/empresas/{empresa}/productos/{producto}/receta')
    ->name('productos.recetas.')
    ->group(function (): void {
        Route::get('/', [RecetaController::class, 'edit'])->name('edit');
        Route::put('/', [RecetaController::class, 'update'])->name('update');
    });

// =========================================================
// INVENTARIO
// Operaciones para registrar entradas, recepciones y
// transferencias de productos en los almacenes (3 fases).
// =========================================================

Route::middleware([
    'auth',
    'verified',
])
    ->prefix('operacion')
    ->name('inventario.')
    ->group(function (): void {
        // -------------------------------------------------
        // ENTRADA SIMPLE
        // -------------------------------------------------
        Route::get(
            '/empresas/{empresa}/inventario/entradas/crear',
            [EntradaInventarioController::class, 'create']
        )
            ->middleware('permission:inventario.registrar_entrada')
            ->name('entradas.create');

        Route::post(
            '/empresas/{empresa}/inventario/entradas',
            [EntradaInventarioController::class, 'store']
        )
            ->middleware('permission:inventario.registrar_entrada')
            ->name('entradas.store');

        // -------------------------------------------------
        // RECEPCIÓN MÚLTIPLE DE INVENTARIO
        // -------------------------------------------------
        Route::get(
            '/empresas/{empresa}/inventario/recepciones/crear',
            [RecepcionInventarioController::class, 'create']
        )
            ->middleware('permission:inventario.registrar_entrada')
            ->name('recepciones.create');

        Route::post(
            '/empresas/{empresa}/inventario/recepciones',
            [RecepcionInventarioController::class, 'store']
        )
            ->middleware('permission:inventario.registrar_entrada')
            ->name('recepciones.store');

        Route::get(
            '/empresas/{empresa}/inventario/recepciones',
            [RecepcionInventarioController::class, 'index']
        )
            ->middleware('permission:inventario.registrar_entrada')
            ->name('recepciones.index');

        Route::get(
            '/empresas/{empresa}/inventario/recepciones/data',
            [RecepcionInventarioController::class, 'data']
        )
            ->middleware('permission:inventario.registrar_entrada')
            ->name('recepciones.data');

        Route::get(
            '/empresas/{empresa}/inventario/ordenes-compra/{orden}/detalles',
            [RecepcionInventarioController::class, 'ordenCompraData']
        )
            ->name('recepciones.orden-data');

        // -------------------------------------------------
        // TRANSFERENCIAS DE INVENTARIO (3 FASES)
        // Solicitud -> Despacho a Tránsito -> Recepción Destino
        // -------------------------------------------------
        Route::prefix('empresas/{empresa}/inventario/transferencias')
            ->name('transferencias.')
            ->group(function (): void {
                Route::get('/', [TransferenciaInventarioController::class, 'index'])->name('index');
                Route::get('/data', [TransferenciaInventarioController::class, 'data'])->name('data');

                // Fase 1: Solicitud / Requisición
                Route::get('/solicitar', [TransferenciaInventarioController::class, 'create'])->name('create');
                Route::post('/', [TransferenciaInventarioController::class, 'store'])->name('store');

                // Fase 2: Despacho y Custodia en Tránsito
                Route::get('/{transferencia}/despachar', [TransferenciaInventarioController::class, 'despacharView'])->name('despachar.view');
                Route::post('/{transferencia}/despachar', [TransferenciaInventarioController::class, 'despacharStore'])->name('despachar.store');

                // Fase 3: Recepción y Validación en Destino
                Route::get('/{transferencia}/recibir', [TransferenciaInventarioController::class, 'recibirView'])->name('recibir.view');
                Route::post('/{transferencia}/recibir', [TransferenciaInventarioController::class, 'recibirStore'])->name('recibir.store');
            });

        // -------------------------------------------------
        // CONSULTA DE EXISTENCIAS Y KARDEX
        // -------------------------------------------------
        Route::get(
            '/empresas/{empresa}/inventario/existencias',
            [InventarioKardexController::class, 'index']
        )->name('existencias.index');

        Route::get(
            '/empresas/{empresa}/inventario/existencias/data',
            [InventarioKardexController::class, 'dataExistencias']
        )->name('existencias.data');

        Route::get(
            '/empresas/{empresa}/inventario/kardex/{producto}/{almacen?}',
            [InventarioKardexController::class, 'kardex']
        )->name('kardex.show');

        Route::get(
            '/empresas/{empresa}/inventario/kardex-data/{producto}/{almacen?}',
            [InventarioKardexController::class, 'dataKardex']
        )->name('kardex.data');

        Route::get(
            '/empresas/{empresa}/inventario/kardex-pdf/{producto}/{almacen?}',
            [InventarioKardexController::class, 'exportarKardexPdf']
        )->name('kardex.pdf');
    });

// =========================================================
// PROVEEDORES
// =========================================================

Route::middleware([
    'auth',
    'verified',
])
    ->prefix('operacion')
    ->name('proveedores.')
    ->group(function (): void {
        Route::get(
            '/empresas/{empresa}/proveedores',
            [ProveedorController::class, 'index']
        )
            ->middleware('permission:productos.ver')
            ->name('index');

        Route::get(
            '/empresas/{empresa}/proveedores/crear',
            [ProveedorController::class, 'create']
        )
            ->middleware('permission:productos.crear')
            ->name('create');

        Route::post(
            '/empresas/{empresa}/proveedores',
            [ProveedorController::class, 'store']
        )
            ->middleware('permission:productos.crear')
            ->name('store');

        Route::get(
            '/empresas/{empresa}/proveedores/{proveedor}/editar',
            [ProveedorController::class, 'edit']
        )
            ->middleware('permission:productos.actualizar')
            ->name('edit');

        Route::put(
            '/empresas/{empresa}/proveedores/{proveedor}',
            [ProveedorController::class, 'update']
        )
            ->middleware('permission:productos.actualizar')
            ->name('update');

        Route::patch(
            '/empresas/{empresa}/proveedores/{proveedor}/estado',
            [ProveedorController::class, 'updateStatus']
        )
            ->middleware('permission:productos.cambiar_estado')
            ->name('estado.update');
    });

// =========================================================
// PUNTO DE VENTA / MOSTRADOR (POS)
// Control de turnos de caja, ventas de mostrador y devoluciones
// =========================================================

Route::middleware([
    'auth',
    'verified',
])
    ->prefix('pos')
    ->name('pos.')
    ->group(function (): void {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::get('/catalogo', [PosController::class, 'catalogo'])->name('catalogo');
        Route::post('/caja/apertura', [PosController::class, 'aperturaCaja'])->name('caja.apertura');
        Route::post('/caja/cierre', [PosController::class, 'cierreCaja'])->name('caja.cierre');
        Route::post('/ventas', [PosController::class, 'storeVenta'])->name('ventas.store');
        Route::post('/sincronizar-offline', [PosController::class, 'sincronizarOffline'])->name('sincronizar.offline');
        Route::get('/ticket/{venta}', [PosController::class, 'ticket'])->name('ticket');
        Route::get('/ultimas-ventas', [PosController::class, 'ultimasVentas'])->name('ultimas-ventas');
        Route::get('/ventas/{uuid}/ticket', [PosController::class, 'reimprimirTicket'])->name('ventas.ticket');

        // Devoluciones en Punto de Venta (exclusivo para supervisores y administradores)
        Route::post('/devoluciones', [PosController::class, 'devolucion'])
            ->middleware('permission:pos.devoluciones')
            ->name('devoluciones.store');
    });

// =========================================================
// ROLES ESPECÍFICOS: SUPERVISOR Y TÉCNICO EMPLEADO
// =========================================================

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

        Route::get(
            '/cotizaciones/data',
            [RevisionCotizacionController::class, 'data']
        )->name('cotizaciones.data');

        Route::get(
            '/cotizaciones/{orden}',
            [RevisionCotizacionController::class, 'show']
        )->name('cotizaciones.show');
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

        Route::get(
            '/ordenes/data',
            [OrdenAsignadaController::class, 'data']
        )->name('ordenes.data');

        Route::patch(
            '/ordenes/{orden}/tecnica',
            [OrdenAsignadaController::class, 'updateTechnical']
        )->name('ordenes.tecnica.update');

        Route::post(
            '/ordenes/{orden}/cotizacion/revision',
            [OrdenAsignadaController::class, 'requestQuoteReview']
        )->name('ordenes.cotizacion.revision');

        Route::get(
            '/ordenes/{orden}',
            [OrdenAsignadaController::class, 'show']
        )->name('ordenes.show');

        Route::post(
            '/ordenes/{orden}/iniciar',
            [OrdenAsignadaController::class, 'startRepair']
        )->name('ordenes.iniciar');

        Route::post(
            '/ordenes/{orden}/pruebas',
            [OrdenAsignadaController::class, 'sendToTesting']
        )->name('ordenes.pruebas');

        Route::post(
            '/ordenes/{orden}/lista-para-entrega',
            [OrdenAsignadaController::class, 'markReadyForDelivery']
        )->name('ordenes.lista-para-entrega');
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

        Route::post(
            '/ordenes/{orden}/cotizacion/aprobar',
            [RevisionCotizacionController::class, 'approve']
        )->name('cotizaciones.aprobar');

        Route::post(
            '/ordenes/{orden}/cotizacion/rechazar',
            [RevisionCotizacionController::class, 'reject']
        )->name('cotizaciones.rechazar');

        Route::post(
            '/ordenes/{orden}/entregar',
            [EntregaOrdenController::class, 'store']
        )->name('ordenes.entregar');
    });

// =========================================================
// ÓRDENES DE COMPRA A PROVEEDORES
// =========================================================

Route::middleware([
    'auth',
    'verified',
])
    ->prefix('operacion/compras')
    ->name('compras.ordenes.')
    ->group(function (): void {
        Route::get('/', [OrdenCompraController::class, 'index'])->name('index');
        Route::get('/crear', [OrdenCompraController::class, 'create'])->name('create');
        Route::post('/', [OrdenCompraController::class, 'store'])->name('store');
        Route::get('/{orden}', [OrdenCompraController::class, 'show'])->name('show');
        Route::get('/{orden}/pdf', [OrdenCompraController::class, 'pdf'])->name('pdf');
        Route::patch('/{orden}/solicitar', [OrdenCompraController::class, 'solicitar'])->name('solicitar');
        Route::patch('/{orden}/autorizar', [OrdenCompraController::class, 'autorizar'])
            ->middleware('permission:compras.autorizar')
            ->name('autorizar');
        Route::patch('/{orden}/cancelar', [OrdenCompraController::class, 'cancelar'])->name('cancelar');
    });

// =========================================================
// AUDITORÍA Y CORTES DE CAJA (SUPERVISIÓN)
// =========================================================

Route::middleware(['auth', 'verified'])
    ->prefix('operacion/cajas')
    ->name('pos.cajas.')
    ->group(function (): void {
        Route::get('/', [CajaAuditoriaController::class, 'index'])->name('index');
        Route::get('/{turno}', [CajaAuditoriaController::class, 'show'])->name('show');
        Route::post('/{turno}/movimiento', [CajaAuditoriaController::class, 'registrarMovimiento'])->name('movimiento');
        Route::get('/{turno}/corte-pdf', [CajaAuditoriaController::class, 'cortePdf'])->name('corte-pdf');
    });

// =========================================================
// FACTURACIÓN ELECTRÓNICA SAT (CFDI 4.0)
// =========================================================

Route::middleware(['auth', 'verified'])
    ->prefix('operacion/facturacion')
    ->name('facturacion.')
    ->group(function (): void {
        Route::post('/ventas/{venta}/timbrar', [FacturacionController::class, 'timbrar'])->name('ventas.timbrar');
        Route::get('/ventas/{venta}/descargar-xml', [FacturacionController::class, 'descargarXml'])->name('ventas.descargar-xml');
        Route::post('/lote-pendientes', [FacturacionController::class, 'procesarLotePendientes'])->name('lote-pendientes');
        Route::get('/ventas/{venta}/descargar-pdf', [FacturacionController::class, 'descargarPdf'])->name('ventas.descargar-pdf');
    });

// =========================================================
// PANTALLAS DE COCINA Y BARRA (KDS - KITCHEN DISPLAY SYSTEM)
// =========================================================
Route::middleware(['auth', 'verified'])
    ->prefix('restaurante/kds')
    ->name('restaurante.kds.')
    ->group(function (): void {
        Route::get('/', [KdsController::class, 'index'])->name('index');
        Route::get('/comandas-activas', [KdsController::class, 'comandasActivas'])->name('comandas-activas');
        Route::patch('/items/{item}/estado', [KdsController::class, 'cambiarEstadoItem'])->name('items.estado');
        Route::post('/comandas/{comanda}/completar', [KdsController::class, 'completarComanda'])->name('completar');
    });

// =========================================================
// COMANDERO MÓVIL EN TABLETS PARA MESEROS
// =========================================================
Route::middleware(['auth', 'verified'])
    ->prefix('restaurante/comandero')
    ->name('restaurante.comandero.')
    ->group(function (): void {
        Route::get('/', [ComanderoController::class, 'index'])->name('index');
        Route::get('/catalogo', [ComanderoController::class, 'catalogo'])->name('catalogo');
        Route::post('/enviar', [ComanderoController::class, 'enviarComanda'])->name('enviar');
    });

// =========================================================
// PLANO GRÁFICO DE MESAS (FLOOR PLAN / DRAG & DROP)
// =========================================================
Route::middleware(['auth', 'verified'])
    ->prefix('restaurante/mesas')
    ->name('restaurante.mesas.')
    ->group(function (): void {
        Route::get('/plano', [PlanoMesasController::class, 'index'])->name('plano');
        Route::post('/guardar-posiciones', [PlanoMesasController::class, 'guardarPosiciones'])
            ->middleware('role:administrador|supervisor')
            ->name('guardar-posiciones');
        Route::post('/crear', [PlanoMesasController::class, 'storeMesa'])
            ->middleware('role:administrador|supervisor')
            ->name('store');
    });

// =========================================================
// PRECUENTA, COBRO Y LIBERACIÓN DE MESAS
// =========================================================
Route::middleware(['auth', 'verified'])
    ->prefix('restaurante/mesas/{mesa}')
    ->name('restaurante.mesas.')
    ->group(function (): void {
        Route::get('/precuenta', [CuentaMesaController::class, 'precuenta'])->name('precuenta');
        Route::patch('/pedir-cuenta', [CuentaMesaController::class, 'pedirCuenta'])->name('pedir-cuenta');
        Route::post('/liquidar', [CuentaMesaController::class, 'liquidarMesa'])->name('liquidar');
    });

// =========================================================
// PORTAL PÚBLICO DE AUTOFACTURACIÓN (ESCÁNER QR EN TICKET)
// =========================================================
Route::prefix('autofactura')->name('autofactura.')->group(function (): void {
    Route::get('/{token}', [AutofacturaController::class, 'show'])->name('show');
    Route::post('/{token}/timbrar', [AutofacturaController::class, 'procesar'])->name('procesar');
});

require __DIR__.'/auth.php';
