<?php

namespace Tests\Feature\Inventario;

use App\Actions\Inventario\RegistrarRecepcionInventario;
use App\Models\Almacen;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\RecepcionInventario;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HistorialRecepcionInventarioTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Almacen $almacen;

    private Proveedor $proveedor;

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

        $this->almacen = Almacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $sucursal->id,
            'codigo' => 'ALM-01',
            'nombre' => 'Almacén Central',
            'tipo' => Almacen::TIPO_PRINCIPAL,
            'es_virtual' => false,
            'permite_existencias' => true,
            'activo' => true,
        ]);

        $this->proveedor = Proveedor::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'PROV-001',
            'nombre' => 'Mayorista Tecnológico',
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
            'sku' => 'SSD-500GB',
            'nombre' => 'SSD Kingston 500GB',
            'unidad_medida' => 'pieza',
            'costo_referencia' => '650.00',
            'precio_base' => '950.00',
            'activo' => true,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
        $this->admin->givePermissionTo('inventario.registrar_entrada');
    }

    #[Test]
    public function usuario_autorizado_puede_ver_vista_de_historial_recepciones(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('inventario.recepciones.index', $this->empresa)
        );

        $response->assertOk();
        $response->assertViewIs('inventario.recepciones.index');
        $response->assertSee('Historial de Recepciones de Inventario');
    }

    #[Test]
    public function feed_json_retorna_datos_formateados_para_datatables(): void
    {
        // Registrar una recepción previa
        app(RegistrarRecepcionInventario::class)->ejecutar(
            empresa: $this->empresa,
            almacen: $this->almacen,
            proveedor: $this->proveedor,
            actor: $this->admin,
            datos: [
                'tipo' => RecepcionInventario::TIPO_COMPRA,
                'referencia_externa' => 'FAC-9988',
                'motivo' => 'Compra mensual de almacenamiento',
                'lineas' => [
                    [
                        'producto_id' => $this->producto->id,
                        'cantidad' => '10.0000',
                        'costo_unitario' => '600.0000',
                    ],
                ],
            ]
        );

        $response = $this->actingAs($this->admin)->getJson(
            route('inventario.recepciones.data', $this->empresa)
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'folio',
                    'tipo',
                    'almacen',
                    'proveedor',
                    'referencia_externa',
                    'motivo',
                    'total_lineas',
                    'importe_total',
                    'recibido_por',
                    'recibido_at',
                    'detalles',
                ],
            ],
        ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Compra', $response->json('data.0.tipo'));
        $this->assertSame('$6,000.00', $response->json('data.0.importe_total'));
    }
}
