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

class LiberarReservaOrdenTest extends TestCase
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
            'sku' => 'FUENTE-650W',
            'nombre' => 'Fuente de Poder 650W Bronze',
            'unidad_medida' => 'pieza',
            'costo_referencia' => '800.00',
            'precio_base' => '1150.00',
            'activo' => true,
        ]);

        $this->cliente = User::factory()->create();
        $this->cliente->assignRole('cliente');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
        $this->admin->givePermissionTo('inventario.consumir');

        $equipo = Equipo::query()->create([
            'user_id' => $this->cliente->id,
            'tipo' => 'pc_escritorio',
            'marca' => 'Custom',
            'modelo' => 'Gamer ATX',
        ]);

        $this->orden = OrdenServicio::query()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $this->cliente->id,
            'equipo_id' => $equipo->id,
            'folio' => 'REP-2026-0003',
            'problema_reportado' => 'No enciende el equipo',
            'fecha_ingreso' => now(),
            'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        ]);
    }

    #[Test]
    public function libera_exitosamente_unidades_de_reserva_activa(): void
    {
        // 1. Stock físico inicial: 3 unidades
        ExistenciaAlmacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'cantidad' => '3.0000',
            'cantidad_reservada' => '0.0000',
            'costo_promedio' => '800.0000',
        ]);

        // 2. Crear reserva previa de 2 unidades
        app(ReservarInventarioParaOrden::class)->ejecutar(
            empresa: $this->empresa,
            almacen: $this->almacen,
            producto: $this->producto,
            orden: $this->orden,
            actor: $this->admin,
            datos: [
                'cantidad' => '2.0000',
                'motivo' => 'Reserva para prueba de fuente',
            ]
        );

        // 3. Liberar las 2 unidades reservadas
        $payload = [
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'cantidad' => '2.0000',
            'motivo' => 'Falla corregida en cable de poder, no requirió cambio de fuente',
        ];

        $response = $this->actingAs($this->admin)->post(
            route('admin.ordenes.inventario.liberar', [
                'empresa' => $this->empresa->id,
                'orden' => $this->orden->id,
            ]),
            $payload
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // La cantidad física se mantiene intacta (3), pero la reservada vuelve a 0
        $this->assertDatabaseHas('existencias_almacen', [
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'cantidad' => '3.0000',
            'cantidad_reservada' => '0.0000',
        ]);

        // La reserva pasa a estado 'liberada'
        $this->assertDatabaseHas('reservas_inventario', [
            'orden_servicio_id' => $this->orden->id,
            'cantidad_reservada' => '2.0000',
            'cantidad_liberada' => '2.0000',
            'cantidad_consumida' => '0.0000',
            'estado' => ReservaInventario::ESTADO_LIBERADA,
        ]);
    }

    #[Test]
    public function rechaza_liberacion_si_la_cantidad_supera_las_unidades_pendientes(): void
    {
        ExistenciaAlmacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'cantidad' => '5.0000',
            'cantidad_reservada' => '0.0000',
            'costo_promedio' => '800.0000',
        ]);

        app(ReservarInventarioParaOrden::class)->ejecutar(
            empresa: $this->empresa,
            almacen: $this->almacen,
            producto: $this->producto,
            orden: $this->orden,
            actor: $this->admin,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Reserva de 1 pieza',
            ]
        );

        // Intento de liberar 2 unidades cuando solo hay 1 reservada
        $payload = [
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'cantidad' => '2.0000',
            'motivo' => 'Intento de liberar de más',
        ];

        $response = $this->actingAs($this->admin)->post(
            route('admin.ordenes.inventario.liberar', [
                'empresa' => $this->empresa->id,
                'orden' => $this->orden->id,
            ]),
            $payload
        );

        $response->assertSessionHasErrors();
    }
}
