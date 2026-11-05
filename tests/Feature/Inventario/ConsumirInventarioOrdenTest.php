<?php

namespace Tests\Feature\Inventario;

use App\Actions\Inventario\ReservarInventarioParaOrden;
use App\Enums\EstadoOrden;
use App\Models\Almacen;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\Equipo;
use App\Models\ExistenciaAlmacen;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\ReservaInventario;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsumirInventarioOrdenTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Sucursal $sucursal;

    private Almacen $almacen;

    private CategoriaProducto $categoria;

    private Producto $producto;

    private User $cliente;

    private User $admin;

    private OrdenServicio $orden;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->empresa = Empresa::query()->create([
            'nombre' => 'Cano Computadoras Test',
            'activo' => true,
        ]);

        $this->sucursal = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-01',
            'nombre' => 'Sucursal Centro',
            'activo' => true,
        ]);

        $this->almacen = Almacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'codigo' => 'ALM-01',
            'nombre' => 'Almacén General',
            'tipo' => Almacen::TIPO_PRINCIPAL,
            'es_virtual' => false,
            'permite_existencias' => true,
            'activo' => true,
        ]);

        $this->categoria = CategoriaProducto::query()->create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Componentes',
            'activo' => true,
        ]);

        $this->producto = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $this->categoria->id,
            'sku' => 'SSD-1TB',
            'nombre' => 'Unidad SSD NVMe 1TB',
            'unidad_medida' => 'pieza',
            'costo_referencia' => '1200.00',
            'precio_base' => '1700.00',
            'activo' => true,
        ]);

        $this->cliente = User::factory()->create();
        $this->cliente->assignRole('cliente');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
        $this->admin->givePermissionTo('inventario.consumir');

        $equipo = Equipo::query()->create([
            'user_id' => $this->cliente->id,
            'tipo' => 'laptop',
            'marca' => 'Dell',
            'modelo' => 'XPS 13',
        ]);

        $this->orden = OrdenServicio::query()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $this->cliente->id,
            'equipo_id' => $equipo->id,
            'folio' => 'REP-2026-0002',
            'problema_reportado' => 'Falla en disco duro',
            'fecha_ingreso' => now(),
            'estado' => EstadoOrden::EN_REPARACION->value,
        ]);
    }

    #[Test]
    public function consume_exitosamente_unidades_de_reserva_activa(): void
    {
        // 1. Crear stock físico inicial (4 unidades)
        ExistenciaAlmacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'cantidad' => '4.0000',
            'cantidad_reservada' => '0.0000',
            'costo_promedio' => '1200.0000',
        ]);

        // 2. Realizar reserva previa de 2 unidades
        app(ReservarInventarioParaOrden::class)->ejecutar(
            empresa: $this->empresa,
            almacen: $this->almacen,
            producto: $this->producto,
            orden: $this->orden,
            actor: $this->admin,
            datos: [
                'cantidad' => '2.0000',
                'motivo' => 'Reserva inicial',
            ]
        );

        // 3. Consumir 2 unidades de la reserva
        $payload = [
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'cantidad' => '2.0000',
            'motivo' => 'Instalación y formateo finalizado',
        ];

        $response = $this->actingAs($this->admin)->post(
            route('admin.ordenes.inventario.consumir', [
                'empresa' => $this->empresa->id,
                'orden' => $this->orden->id,
            ]),
            $payload
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Stock físico debió bajar de 4 a 2, y reservado de 2 a 0
        $this->assertDatabaseHas('existencias_almacen', [
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'cantidad' => '2.0000',
            'cantidad_reservada' => '0.0000',
        ]);

        // Reserva pasa a estado 'consumida'
        $this->assertDatabaseHas('reservas_inventario', [
            'orden_servicio_id' => $this->orden->id,
            'cantidad_reservada' => '2.0000',
            'cantidad_consumida' => '2.0000',
            'estado' => ReservaInventario::ESTADO_CONSUMIDA,
        ]);

        // Se debió registrar el movimiento contable de salida
        $this->assertDatabaseHas('movimientos_inventario', [
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'tipo' => 'consumo',
            'cantidad' => '2.0000',
        ]);
    }

    #[Test]
    public function rechaza_consumo_si_la_cantidad_supera_lo_reservado(): void
    {
        ExistenciaAlmacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'cantidad' => '5.0000',
            'cantidad_reservada' => '0.0000',
            'costo_promedio' => '1200.0000',
        ]);

        app(ReservarInventarioParaOrden::class)->ejecutar(
            empresa: $this->empresa,
            almacen: $this->almacen,
            producto: $this->producto,
            orden: $this->orden,
            actor: $this->admin,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Reserva de 1 unidad',
            ]
        );

        // Intento de consumir 2 unidades cuando solo hay 1 reservada
        $payload = [
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'cantidad' => '2.0000',
            'motivo' => 'Exceso de consumo',
        ];

        $response = $this->actingAs($this->admin)->post(
            route('admin.ordenes.inventario.consumir', [
                'empresa' => $this->empresa->id,
                'orden' => $this->orden->id,
            ]),
            $payload
        );

        $response->assertSessionHasErrors();
    }
}
