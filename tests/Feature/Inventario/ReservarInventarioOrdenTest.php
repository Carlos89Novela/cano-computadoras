<?php

namespace Tests\Feature\Inventario;

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

class ReservarInventarioOrdenTest extends TestCase
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
            'sku' => 'RAM-16GB',
            'nombre' => 'Memoria RAM DDR4 16GB',
            'unidad_medida' => 'pieza',
            'costo_referencia' => '650.00',
            'precio_base' => '950.00',
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
            'modelo' => 'Inspiron 15',
        ]);

        $this->orden = OrdenServicio::query()->create([
            'empresa_id' => $this->empresa->id,
            'user_id' => $this->cliente->id,
            'equipo_id' => $equipo->id,
            'folio' => 'REP-2026-0001',
            'problema_reportado' => 'Falla en memoria RAM',
            'fecha_ingreso' => now(),
            'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        ]);
    }

    #[Test]
    public function reserva_correctamente_refaccion_cuando_hay_disponibilidad(): void
    {
        ExistenciaAlmacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'cantidad' => '5.0000',
            'cantidad_reservada' => '0.0000',
            'costo_promedio' => '650.0000',
        ]);

        $payload = [
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'cantidad' => '2.0000',
            'motivo' => 'Asignado para reemplazo de módulo dañado',
        ];

        $response = $this->actingAs($this->admin)->post(
            route('admin.ordenes.inventario.reservar', [
                'empresa' => $this->empresa->id,
                'orden' => $this->orden->id,
            ]),
            $payload
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('existencias_almacen', [
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'cantidad' => '5.0000',
            'cantidad_reservada' => '2.0000',
        ]);

        $this->assertDatabaseHas('reservas_inventario', [
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'orden_servicio_id' => $this->orden->id,
            'cantidad_reservada' => '2.0000',
            'cantidad_consumida' => '0.0000',
            'cantidad_liberada' => '0.0000',
            'estado' => ReservaInventario::ESTADO_ACTIVA,
        ]);
    }

    #[Test]
    public function rechaza_reserva_si_las_existencias_disponibles_son_insuficientes(): void
    {
        ExistenciaAlmacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'cantidad' => '2.0000',
            'cantidad_reservada' => '2.0000',
            'costo_promedio' => '650.0000',
        ]);

        $payload = [
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'cantidad' => '1.0000',
            'motivo' => 'Intento de reserva sin disponibilidad',
        ];

        $response = $this->actingAs($this->admin)->post(
            route('admin.ordenes.inventario.reservar', [
                'empresa' => $this->empresa->id,
                'orden' => $this->orden->id,
            ]),
            $payload
        );

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('reservas_inventario', [
            'orden_servicio_id' => $this->orden->id,
        ]);
    }

    #[Test]
    public function rechaza_reserva_si_el_usuario_no_tiene_el_permiso(): void
    {
        $usuarioSinPermiso = User::factory()->create();
        $usuarioSinPermiso->assignRole('cliente');

        $payload = [
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'cantidad' => '1.0000',
            'motivo' => 'Reserva no autorizada',
        ];

        $response = $this->actingAs($usuarioSinPermiso)->post(
            route('admin.ordenes.inventario.reservar', [
                'empresa' => $this->empresa->id,
                'orden' => $this->orden->id,
            ]),
            $payload
        );

        $response->assertForbidden();
    }
}
