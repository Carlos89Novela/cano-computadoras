<?php

namespace Tests\Feature\Restaurante;

use App\Models\Almacen;
use App\Models\CategoriaProducto;
use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\ProductoReceta;
use App\Models\ProductoRecetaDetalle;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\Restaurante\RestauranteReporteService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReporteRestauranteTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Sucursal $sucursal;

    private User $administrador;

    private User $empleado;

    private Producto $platilloPizza;

    private Producto $insumoQueso;

    private Producto $insumoHarina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->empresa = Empresa::query()->create([
            'nombre' => 'Pizzería & Grill Analytics',
            'giro_comercial' => 'restaurante_cafeteria',
            'plan_suscripcion' => 'avanzado',
            'activo' => true,
        ]);

        $this->sucursal = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-REP',
            'nombre' => 'Sucursal Principal',
            'activo' => true,
        ]);

        Almacen::query()->create([
            'id' => 1,
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'codigo' => 'ALM-1',
            'nombre' => 'Almacén Central',
            'tipo' => Almacen::TIPO_PRINCIPAL,
            'es_virtual' => false,
            'permite_existencias' => true,
            'activo' => true,
        ]);

        $this->administrador = User::factory()->create();
        $this->administrador->assignRole('administrador');
        $this->sucursal->usuarios()->attach($this->administrador->id, [
            'es_gerente' => true,
            'es_principal' => true,
            'activo' => true,
        ]);

        $this->empleado = User::factory()->create();
        $this->empleado->assignRole('empleado');
        $this->sucursal->usuarios()->attach($this->empleado->id, [
            'es_gerente' => false,
            'es_principal' => true,
            'activo' => true,
        ]);

        $categoria = CategoriaProducto::query()->create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Especialidades',
            'activo' => true,
        ]);

        // 1. Insumos
        $this->insumoQueso = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $categoria->id,
            'sku' => 'INS-QUESO',
            'nombre' => 'Queso Mozzarella',
            'unidad_medida' => 'kg',
            'costo_referencia' => 150.00,
            'precio_base' => 150.00,
            'es_receta' => false,
            'activo' => true,
        ]);

        $this->insumoHarina = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $categoria->id,
            'sku' => 'INS-HARINA',
            'nombre' => 'Harina de Trigo',
            'unidad_medida' => 'kg',
            'costo_referencia' => 20.00,
            'precio_base' => 20.00,
            'es_receta' => false,
            'activo' => true,
        ]);

        // 2. Platillo terminado con receta
        $this->platilloPizza = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $categoria->id,
            'sku' => 'PLAT-PIZZA-SUP',
            'nombre' => 'Pizza Suprema Artesanal',
            'unidad_medida' => 'pieza',
            'costo_referencia' => 39.30,
            'precio_base' => 250.00,
            'es_receta' => true,
            'activo' => true,
        ]);

        $receta = ProductoReceta::query()->create([
            'empresa_id' => $this->empresa->id,
            'producto_id' => $this->platilloPizza->id,
            'estacion_cocina' => 'cocina',
            'tiempo_preparacion_minutos' => 15,
            'activo' => true,
        ]);

        // Ingrediente 1: Queso (0.200 kg con 10% merma) => factor 1.10 => costo $33.00
        ProductoRecetaDetalle::query()->create([
            'producto_receta_id' => $receta->id,
            'insumo_producto_id' => $this->insumoQueso->id,
            'cantidad_requerida' => 0.200,
            'porcentaje_merma' => 10.00,
        ]);

        // Ingrediente 2: Harina (0.300 kg con 5% merma) => factor 1.05 => costo $6.30
        ProductoRecetaDetalle::query()->create([
            'producto_receta_id' => $receta->id,
            'insumo_producto_id' => $this->insumoHarina->id,
            'cantidad_requerida' => 0.300,
            'porcentaje_merma' => 5.00,
        ]);
    }

    #[Test]
    public function calcula_metricas_correctas_de_ventas_kds_margenes_y_mermas(): void
    {
        // 1. Simular ventas completadas del platillo
        $venta = new Venta;
        $venta->forceFill([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'almacen_id' => 1,
            'user_id' => $this->administrador->id,
            'folio' => 'VTA-REP-001',
            'estado' => 'completada',
            'base_imponible' => 1250.00,
            'iva_total' => 200.00,
            'total' => 1250.00,
        ]);
        $venta->save();

        VentaDetalle::query()->create([
            'venta_id' => $venta->id,
            'almacen_id' => 1,
            'producto_id' => $this->platilloPizza->id,
            'descripcion_producto' => $this->platilloPizza->nombre,
            'sku' => $this->platilloPizza->sku,
            'cantidad' => 5.00,
            'precio_unitario' => 250.00,
            'subtotal' => 1250.00,
            'total' => 1250.00,
        ]);

        // 2. Simular comanda finalizada para tiempos de cocina KDS y mermas
        $comanda = Comanda::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'mesero_user_id' => $this->administrador->id,
            'folio' => 'CMD-REP-001',
            'mesa_o_referencia' => 'Mesa 1',
            'estado' => 'completada',
            'enviada_a_cocina_at' => now()->subMinutes(18),
            'finalizada_at' => now(),
        ]);

        ComandaItem::query()->create([
            'comanda_id' => $comanda->id,
            'producto_id' => $this->platilloPizza->id,
            'cantidad' => 5.00,
            'estacion_cocina' => 'cocina',
            'estado' => 'listo',
        ]);

        /** @var RestauranteReporteService $service */
        $service = app(RestauranteReporteService::class);
        $metricas = $service->obtenerMetricasPeriodo($this->empresa, $this->sucursal);

        // Aserciones de Top Platillos
        $this->assertNotEmpty($metricas['top_platillos']);
        $this->assertSame('Pizza Suprema Artesanal', $metricas['top_platillos'][0]['nombre']);
        $this->assertEquals(5.0, $metricas['top_platillos'][0]['cantidad']);
        $this->assertEquals(1250.0, $metricas['top_platillos'][0]['total']);

        // Aserciones de KDS (tiempo promedio ~18 minutos en cocina)
        $this->assertArrayHasKey('cocina', $metricas['metricas_kds']);
        $this->assertSame(1, $metricas['metricas_kds']['cocina']['cantidad']);
        $this->assertEquals(18.0, $metricas['metricas_kds']['cocina']['promedio_minutos']);

        // Aserciones de Márgenes teóricos de Recetas
        $this->assertNotEmpty($metricas['margenes_recetas']);
        $recetaData = $metricas['margenes_recetas'][0];
        $this->assertSame('Pizza Suprema Artesanal', $recetaData['nombre']);
        $this->assertEquals(39.30, $recetaData['costo_teorico']);
        $this->assertEquals(250.00, $recetaData['precio_venta']);
        $this->assertEquals(84.3, $recetaData['margen_porcentaje']);

        // Aserciones de Mermas acumuladas (5 piezas preparadas):
        // Queso: 0.200 * 0.10 * 5 = 0.100 kg ($15.00)
        // Harina: 0.300 * 0.05 * 5 = 0.075 kg ($1.50)
        $this->assertCount(2, $metricas['mermas_acumuladas']);

        $mermasMap = collect($metricas['mermas_acumuladas'])->keyBy('insumo');
        $this->assertTrue($mermasMap->has('Queso Mozzarella'));
        $this->assertEquals(0.10, $mermasMap->get('Queso Mozzarella')['merma_total']);
        $this->assertEquals(15.00, $mermasMap->get('Queso Mozzarella')['costo_merma']);

        $this->assertTrue($mermasMap->has('Harina de Trigo'));
        $this->assertEquals(0.075, $mermasMap->get('Harina de Trigo')['merma_total']);
        $this->assertEquals(1.50, $mermasMap->get('Harina de Trigo')['costo_merma']);
    }

    #[Test]
    public function supervisor_o_administrador_pueden_ver_el_panel_y_empleado_recibe_prohibido(): void
    {
        // 1. Acceso como Administrador
        $responseAdmin = $this->actingAs($this->administrador)
            ->get(route('restaurante.reportes.index'));

        $responseAdmin->assertOk();
        $responseAdmin->assertSee('Panel de Analítica y Rendimiento');
        $responseAdmin->assertSee('Tiempo Promedio de Preparación en Cocina (KDS)');

        // 2. Acceso como Empleado (Forbidden 403)
        $responseEmpleado = $this->actingAs($this->empleado)
            ->get(route('restaurante.reportes.index'));

        $responseEmpleado->assertForbidden();
    }
}
