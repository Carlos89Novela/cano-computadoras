<?php

namespace Tests\Feature\Inventario;

use App\Actions\Inventario\RegistrarRecepcionInventario;
use App\Models\Almacen;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\ExistenciaAlmacen;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\RecepcionInventario;
use App\Models\Sucursal;
use App\Models\TransferenciaInventario;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TransferenciaInventarioTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Almacen $almacenOrigen;

    private Almacen $almacenDestino;

    private Producto $producto;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->empresa = Empresa::query()->create([
            'nombre' => 'Cano Computadoras Test',
            'activo' => true,
        ]);

        $sucursalOrigen = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-MATRIZ',
            'nombre' => 'Sucursal Matriz',
            'activo' => true,
        ]);

        $sucursalDestino = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-NORTE',
            'nombre' => 'Sucursal Norte',
            'activo' => true,
        ]);

        $this->almacenOrigen = Almacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $sucursalOrigen->id,
            'codigo' => 'ALM-ORIGEN',
            'nombre' => 'Almacén Principal Matriz',
            'tipo' => Almacen::TIPO_PRINCIPAL,
            'es_virtual' => false,
            'permite_existencias' => true,
            'activo' => true,
        ]);

        $this->almacenDestino = Almacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $sucursalDestino->id,
            'codigo' => 'ALM-DESTINO',
            'nombre' => 'Almacén Sucursal Norte',
            'tipo' => Almacen::TIPO_SECUNDARIO,
            'es_virtual' => false,
            'permite_existencias' => true,
            'activo' => true,
        ]);

        $categoria = CategoriaProducto::query()->create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Discos',
            'activo' => true,
        ]);

        $this->producto = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $categoria->id,
            'sku' => 'SSD-1TB',
            'nombre' => 'Unidad SSD NVMe 1TB',
            'unidad_medida' => 'pieza',
            'costo_referencia' => '1000.00',
            'precio_base' => '1500.00',
            'activo' => true,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');

        Permission::findOrCreate('inventario.transferir', 'web');
        Permission::findOrCreate('inventario.registrar_entrada', 'web');

        $this->admin->givePermissionTo('inventario.transferir');
        $this->admin->givePermissionTo('inventario.registrar_entrada');

        // Carga inicial en origen (10 unidades a $1,000 c/u)
        $proveedor = Proveedor::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'PROV-01',
            'nombre' => 'Mayorista Tecnológico',
            'activo' => true,
        ]);

        app(RegistrarRecepcionInventario::class)->ejecutar(
            empresa: $this->empresa,
            almacen: $this->almacenOrigen,
            proveedor: $proveedor,
            actor: $this->admin,
            datos: [
                'tipo' => RecepcionInventario::TIPO_COMPRA,
                'motivo' => 'Carga inicial para prueba de traspaso',
                'lineas' => [
                    [
                        'producto_id' => $this->producto->id,
                        'cantidad' => '10.0000',
                        'costo_unitario' => '1000.0000',
                    ],
                ],
            ]
        );
    }

    #[Test]
    public function ejecuta_ciclo_completo_solicitud_despacho_y_recepcion_con_custodia_en_transito(): void
    {
        // --------------------------------------------------------------------
        // FASE 1: Solicitud / Requisición (Sucursal Destino pide a Origen)
        // --------------------------------------------------------------------
        $responseSolicitud = $this->actingAs($this->admin)->post(
            route('inventario.transferencias.store', $this->empresa),
            [
                'almacen_origen_id' => $this->almacenOrigen->id,
                'almacen_destino_id' => $this->almacenDestino->id,
                'motivo' => 'Reabastecimiento de mostrador norte',
                'lineas' => [
                    [
                        'producto_id' => $this->producto->id,
                        'cantidad' => '4.0000',
                    ],
                ],
            ]
        );

        $responseSolicitud->assertRedirect(route('inventario.transferencias.index', $this->empresa));

        /** @var TransferenciaInventario $transferencia */
        $transferencia = TransferenciaInventario::query()->firstOrFail();
        $this->assertSame(TransferenciaInventario::ESTADO_SOLICITADA, $transferencia->estado);

        // En Fase 1 no hay afectación de stock físico en origen ni destino
        $stockOrigenFase1 = ExistenciaAlmacen::query()
            ->where('almacen_id', $this->almacenOrigen->id)
            ->where('producto_id', $this->producto->id)
            ->first();
        $this->assertSame('10.0000', (string) $stockOrigenFase1?->cantidad);

        $stockDestinoFase1 = ExistenciaAlmacen::query()
            ->where('almacen_id', $this->almacenDestino->id)
            ->where('producto_id', $this->producto->id)
            ->first();
        $this->assertNull($stockDestinoFase1);

        // --------------------------------------------------------------------
        // FASE 2: Despacho a Tránsito (Sucursal Origen autoriza y envía)
        // --------------------------------------------------------------------
        $detalle = $transferencia->detalles()->firstOrFail();

        $responseDespacho = $this->actingAs($this->admin)->post(
            route('inventario.transferencias.despachar.store', [$this->empresa, $transferencia]),
            [
                'observaciones' => 'Entregado al repartidor en ruta matriz',
                'lineas' => [
                    [
                        'detalle_id' => $detalle->id,
                        'cantidad_despachada' => '4.0000',
                    ],
                ],
            ]
        );

        $responseDespacho->assertRedirect(route('inventario.transferencias.index', $this->empresa));
        $transferencia->refresh();
        $this->assertSame(TransferenciaInventario::ESTADO_EN_TRANSITO, $transferencia->estado);

        // Origen descuenta 4 (quedan 6)
        $stockOrigenFase2 = ExistenciaAlmacen::query()
            ->where('almacen_id', $this->almacenOrigen->id)
            ->where('producto_id', $this->producto->id)
            ->first();
        $this->assertSame('6.0000', (string) $stockOrigenFase2?->cantidad);

        // Tránsito ahora tiene 4 unidades en custodia
        $almacenTransito = Almacen::query()->where('tipo', Almacen::TIPO_TRANSITO)->firstOrFail();
        $stockTransitoFase2 = ExistenciaAlmacen::query()
            ->where('almacen_id', $almacenTransito->id)
            ->where('producto_id', $this->producto->id)
            ->first();
        $this->assertSame('4.0000', (string) $stockTransitoFase2?->cantidad);

        // Destino AÚN no tiene existencias
        $stockDestinoFase2 = ExistenciaAlmacen::query()
            ->where('almacen_id', $this->almacenDestino->id)
            ->where('producto_id', $this->producto->id)
            ->first();
        $this->assertNull($stockDestinoFase2);

        // Bitácora de despacho
        $this->assertDatabaseHas('movimientos_inventario', [
            'almacen_id' => $this->almacenOrigen->id,
            'producto_id' => $this->producto->id,
            'tipo' => MovimientoInventario::TIPO_TRANSFERENCIA_SALIDA,
            'cantidad' => '4.0000',
        ]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'almacen_id' => $almacenTransito->id,
            'producto_id' => $this->producto->id,
            'tipo' => MovimientoInventario::TIPO_TRANSFERENCIA_TRANSITO,
            'cantidad' => '4.0000',
        ]);

        // --------------------------------------------------------------------
        // FASE 3: Recepción y Validación en Destino
        // --------------------------------------------------------------------
        $responseRecepcion = $this->actingAs($this->admin)->post(
            route('inventario.transferencias.recibir.store', [$this->empresa, $transferencia]),
            [
                'observaciones' => 'Recibido completo en mostrador norte',
                'lineas' => [
                    [
                        'detalle_id' => $detalle->id,
                        'cantidad_recibida' => '4.0000',
                    ],
                ],
            ]
        );

        $responseRecepcion->assertRedirect(route('inventario.transferencias.index', $this->empresa));
        $transferencia->refresh();
        $this->assertSame(TransferenciaInventario::ESTADO_COMPLETADA, $transferencia->estado);

        // Almacén de tránsito queda liquidado (0 unidades)
        $stockTransitoFase3 = ExistenciaAlmacen::query()
            ->where('almacen_id', $almacenTransito->id)
            ->where('producto_id', $this->producto->id)
            ->first();
        $this->assertSame('0.0000', (string) $stockTransitoFase3?->cantidad);

        // Destino ingresa las 4 unidades
        $stockDestinoFase3 = ExistenciaAlmacen::query()
            ->where('almacen_id', $this->almacenDestino->id)
            ->where('producto_id', $this->producto->id)
            ->first();
        $this->assertSame('4.0000', (string) $stockDestinoFase3?->cantidad);

        // Bitácora de entrada en destino
        $this->assertDatabaseHas('movimientos_inventario', [
            'almacen_id' => $this->almacenDestino->id,
            'producto_id' => $this->producto->id,
            'tipo' => MovimientoInventario::TIPO_TRANSFERENCIA_ENTRADA,
            'cantidad' => '4.0000',
        ]);
    }

    #[Test]
    public function bloquea_despacho_a_usuario_no_asignado_a_la_sucursal_origen(): void
    {
        // Usuario supervisor asignado exclusivamente a Sucursal Norte
        $supervisorNorte = User::factory()->create();
        $supervisorNorte->assignRole('supervisor');
        Permission::findOrCreate('inventario.transferir', 'web');
        $supervisorNorte->givePermissionTo('inventario.transferir');

        $sucursalNorte = Sucursal::query()->where('codigo', 'SUC-NORTE')->firstOrFail();
        $supervisorNorte->sucursales()->attach($sucursalNorte->id, [
            'activo' => true,
            'es_principal' => true,
        ]);

        // Se crea una requisición donde el origen es Matriz y destino es Norte
        /** @var TransferenciaInventario $transferencia */
        $transferencia = TransferenciaInventario::query()->create([
            'empresa_id' => $this->empresa->id,
            'almacen_origen_id' => $this->almacenOrigen->id,
            'almacen_destino_id' => $this->almacenDestino->id,
            'grupo_id' => (string) Str::uuid(),
            'folio' => 'TRF-TEST-SEGURIDAD',
            'estado' => TransferenciaInventario::ESTADO_SOLICITADA,
            'motivo' => 'Prueba de restricción por sucursal',
            'total_lineas' => 1,
            'costo_total' => '1000.0000',
            'creado_por_id' => $this->admin->id,
            'solicitado_por_id' => $this->admin->id,
            'transferido_at' => now(),
            'solicitado_at' => now(),
        ]);

        // El supervisor de Sucursal Norte intenta despachar desde Almacén Matriz -> Debe retornar 403 Forbidden
        $response = $this->actingAs($supervisorNorte)->get(
            route('inventario.transferencias.despachar.view', [$this->empresa, $transferencia])
        );

        $response->assertStatus(403);
    }
}
