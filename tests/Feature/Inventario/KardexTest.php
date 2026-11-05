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
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KardexTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Sucursal $sucursalMatriz;

    private Sucursal $sucursalNorte;

    private Almacen $almacenMatriz;

    private Almacen $almacenNorte;

    private Producto $producto;

    private User $adminGlobal;

    private User $supervisorNorte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->empresa = Empresa::query()->create([
            'nombre' => 'Cano Computadoras Test',
            'activo' => true,
        ]);

        $this->sucursalMatriz = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-MATRIZ',
            'nombre' => 'Sucursal Matriz',
            'activo' => true,
        ]);

        $this->sucursalNorte = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-NORTE',
            'nombre' => 'Sucursal Norte',
            'activo' => true,
        ]);

        $this->almacenMatriz = Almacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursalMatriz->id,
            'codigo' => 'ALM-MATRIZ',
            'nombre' => 'Almacén Central Matriz',
            'tipo' => Almacen::TIPO_PRINCIPAL,
            'es_virtual' => false,
            'permite_existencias' => true,
            'activo' => true,
        ]);

        $this->almacenNorte = Almacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursalNorte->id,
            'codigo' => 'ALM-NORTE',
            'nombre' => 'Almacén Sucursal Norte',
            'tipo' => Almacen::TIPO_SECUNDARIO,
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
            'nombre' => 'SSD Kingston NVMe 2TB',
            'unidad_medida' => 'pieza',
            'costo_referencia' => '2000.00',
            'precio_base' => '2800.00',
            'activo' => true,
        ]);

        $this->adminGlobal = User::factory()->create();
        $this->adminGlobal->assignRole('administrador');

        Permission::findOrCreate('inventario.transferir', 'web');
        Permission::findOrCreate('inventario.registrar_entrada', 'web');

        $this->adminGlobal->givePermissionTo('inventario.transferir');
        $this->adminGlobal->givePermissionTo('inventario.registrar_entrada');

        $this->supervisorNorte = User::factory()->create();
        $this->supervisorNorte->assignRole('supervisor');
        $this->supervisorNorte->givePermissionTo('inventario.transferir');
        $this->supervisorNorte->givePermissionTo('inventario.registrar_entrada');

        // Asignación estricta a Sucursal Norte
        $this->supervisorNorte->sucursales()->attach($this->sucursalNorte->id, [
            'activo' => true,
            'es_principal' => true,
        ]);

        // Carga inicial en Matriz (10 unidades a $2,000)
        $proveedor = Proveedor::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'PROV-KARDEX',
            'nombre' => 'Mayorista de Hardware',
            'activo' => true,
        ]);

        app(RegistrarRecepcionInventario::class)->ejecutar(
            empresa: $this->empresa,
            almacen: $this->almacenMatriz,
            proveedor: $proveedor,
            actor: $this->adminGlobal,
            datos: [
                'tipo' => RecepcionInventario::TIPO_COMPRA,
                'motivo' => 'Recepción inicial para prueba de Kardex',
                'lineas' => [
                    [
                        'producto_id' => $this->producto->id,
                        'cantidad' => '10.0000',
                        'costo_unitario' => '2000.0000',
                    ],
                ],
            ]
        );
    }

    #[Test]
    public function admin_global_visualiza_existencias_de_todas_las_sucursales(): void
    {
        $response = $this->actingAs($this->adminGlobal)->getJson(
            route('inventario.existencias.data', $this->empresa)
        );

        $response->assertOk();
        $response->assertJsonFragment([
            'sku' => 'NVME-2TB',
            'cantidad' => '10.00',
            'costo_promedio' => '$2,000.00',
        ]);
    }

    #[Test]
    public function supervisor_local_no_visualiza_existencias_de_otras_sucursales(): void
    {
        $response = $this->actingAs($this->supervisorNorte)->getJson(
            route('inventario.existencias.data', $this->empresa)
        );

        $response->assertOk();
        // El stock está en Matriz, el supervisor de Norte no debe ver ese registro
        $data = $response->json('data');
        $this->assertEmpty($data);
    }

    #[Test]
    public function kardex_registra_y_muestra_historial_de_movimientos_con_saldo_actualizado(): void
    {
        $response = $this->actingAs($this->adminGlobal)->getJson(
            route('inventario.kardex.data', [
                'empresa' => $this->empresa,
                'producto' => $this->producto,
                'almacen' => $this->almacenMatriz->id,
            ])
        );

        $response->assertOk();
        $response->assertJsonFragment([
            'tipo' => 'entrada',
            'cantidad' => '10.00',
            'existencia_anterior' => '0.00',
            'existencia_nueva' => '10.00',
            'costo_unitario' => '$2,000.00',
        ]);
    }

    #[Test]
    public function supervisor_no_puede_consultar_kardex_de_almacen_ajeno(): void
    {
        $response = $this->actingAs($this->supervisorNorte)->get(
            route('inventario.kardex.show', [
                'empresa' => $this->empresa,
                'producto' => $this->producto,
                'almacen' => $this->almacenMatriz->id,
            ])
        );

        $response->assertStatus(403);
    }
}
