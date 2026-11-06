<?php

namespace Tests\Feature\Inventario;

use App\Models\Almacen;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KardexTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Almacen $almacen;

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
            'nombre' => 'Sucursal Principal',
            'activo' => true,
        ]);

        $this->almacen = Almacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $sucursal->id,
            'codigo' => 'ALM-01',
            'nombre' => 'Almacén General',
            'tipo' => Almacen::TIPO_PRINCIPAL,
            'es_virtual' => false,
            'permite_existencias' => true,
            'activo' => true,
        ]);

        $categoria = CategoriaProducto::query()->create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Almacenamiento',
            'activo' => true,
        ]);

        $this->producto = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $categoria->id,
            'sku' => 'NVME-2TB',
            'nombre' => 'SSD NVMe Kingston 2TB',
            'unidad_medida' => 'pieza',
            'costo_referencia' => 1800.00,
            'precio_base' => 2400.00,
            'activo' => true,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
    }

    #[Test]
    public function usuario_puede_ver_vista_y_datos_de_kardex(): void
    {
        MovimientoInventario::query()->create([
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'user_id' => $this->admin->id,
            'folio' => 'MOV-TEST-001',
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'cantidad' => 10.0000,
            'existencia_anterior' => 0.0000,
            'existencia_nueva' => 10.0000,
            'costo_unitario' => 1800.0000,
            'motivo' => 'Carga inicial de stock',
            'ocurrido_at' => now(),
        ]);

        $responseView = $this->actingAs($this->admin)->get(
            route('inventario.kardex.show', [
                'empresa' => $this->empresa,
                'producto' => $this->producto,
                'almacen' => $this->almacen,
            ])
        );

        $responseView->assertOk();
        $responseView->assertViewIs('inventario.existencias.kardex');
        $responseView->assertSee('SSD NVMe Kingston 2TB');

        $responseData = $this->actingAs($this->admin)->get(
            route('inventario.kardex.data', [
                'empresa' => $this->empresa,
                'producto' => $this->producto,
                'almacen' => $this->almacen,
            ])
        );

        $responseData->assertOk();
        $responseData->assertJsonFragment(['folio' => 'MOV-TEST-001']);
    }

    #[Test]
    public function usuario_puede_descargar_kardex_en_pdf(): void
    {
        MovimientoInventario::query()->create([
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
            'user_id' => $this->admin->id,
            'folio' => 'MOV-TEST-002',
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'cantidad' => 2.0000,
            'existencia_anterior' => 10.0000,
            'existencia_nueva' => 8.0000,
            'costo_unitario' => 1800.0000,
            'motivo' => 'Venta POS mostrador',
            'ocurrido_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(
            route('inventario.kardex.pdf', [
                'empresa' => $this->empresa,
                'producto' => $this->producto,
                'almacen' => $this->almacen,
            ])
        );

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }
}
