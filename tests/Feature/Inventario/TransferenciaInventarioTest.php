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
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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

        $sucursal = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-01',
            'nombre' => 'Sucursal Centro',
            'activo' => true,
        ]);

        $this->almacenOrigen = Almacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $sucursal->id,
            'codigo' => 'ALM-ORIGEN',
            'nombre' => 'Almacén Principal',
            'tipo' => Almacen::TIPO_PRINCIPAL,
            'es_virtual' => false,
            'permite_existencias' => true,
            'activo' => true,
        ]);

        $this->almacenDestino = Almacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $sucursal->id,
            'codigo' => 'ALM-DESTINO',
            'nombre' => 'Almacén Taller',
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
            'sku' => 'NVME-1TB',
            'nombre' => 'SSD NVMe 1TB',
            'unidad_medida' => 'pieza',
            'costo_referencia' => '1000.00',
            'precio_base' => '1500.00',
            'activo' => true,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');

        // Ingresar existencia en almacén de origen
        $proveedor = Proveedor::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'PROV-01',
            'nombre' => 'Mayorista',
            'activo' => true,
        ]);

        $this->admin->givePermissionTo('inventario.registrar_entrada');

        app(RegistrarRecepcionInventario::class)->ejecutar(
            empresa: $this->empresa,
            almacen: $this->almacenOrigen,
            proveedor: $proveedor,
            actor: $this->admin,
            datos: [
                'tipo' => RecepcionInventario::TIPO_COMPRA,
                'motivo' => 'Carga inicial para prueba',
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
    public function transfiere_inventario_correctamente_y_asienta_movimientos_contables(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('inventario.transferencias.store', $this->empresa),
            [
                'almacen_origen_id' => $this->almacenOrigen->id,
                'almacen_destino_id' => $this->almacenDestino->id,
                'motivo' => 'Envío de stock a taller',
                'lineas' => [
                    [
                        'producto_id' => $this->producto->id,
                        'cantidad' => '4.0000',
                    ],
                ],
            ]
        );

        $response->assertRedirect(route('inventario.transferencias.index', $this->empresa));

        // Origen debe quedar con 6
        $stockOrigen = ExistenciaAlmacen::query()
            ->where('almacen_id', $this->almacenOrigen->id)
            ->where('producto_id', $this->producto->id)
            ->first();
        $this->assertSame('6.0000', (string) $stockOrigen?->cantidad);

        // Destino debe quedar con 4
        $stockDestino = ExistenciaAlmacen::query()
            ->where('almacen_id', $this->almacenDestino->id)
            ->where('producto_id', $this->producto->id)
            ->first();
        $this->assertSame('4.0000', (string) $stockDestino?->cantidad);

        // Se deben haber creado movimientos de salida y entrada
        $this->assertDatabaseHas('movimientos_inventario', [
            'almacen_id' => $this->almacenOrigen->id,
            'producto_id' => $this->producto->id,
            'tipo' => MovimientoInventario::TIPO_TRANSFERENCIA_SALIDA,
            'cantidad' => '4.0000',
        ]);

        $this->assertDatabaseHas('movimientos_inventario', [
            'almacen_id' => $this->almacenDestino->id,
            'producto_id' => $this->producto->id,
            'tipo' => MovimientoInventario::TIPO_TRANSFERENCIA_ENTRADA,
            'cantidad' => '4.0000',
        ]);
    }
}
