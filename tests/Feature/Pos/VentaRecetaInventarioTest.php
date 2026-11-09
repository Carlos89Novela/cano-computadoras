<?php

namespace Tests\Feature\Pos;

use App\Models\Almacen;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\ExistenciaAlmacen;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\ProductoReceta;
use App\Models\ProductoRecetaDetalle;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Pos\RegistrarVentaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VentaRecetaInventarioTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Sucursal $sucursal;

    private Almacen $almacen;

    private User $cajero;

    private Producto $platilloHamburguesa;

    private Producto $insumoCarne;

    private Producto $insumoPan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->empresa = Empresa::query()->create([
            'nombre' => 'Restaurante Burger Test',
            'giro_comercial' => 'restaurante_cafeteria',
            'plan_suscripcion' => 'avanzado',
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
            'codigo' => 'ALM-COCINA',
            'nombre' => 'Almacén Cocina',
            'tipo' => Almacen::TIPO_PRINCIPAL,
            'es_virtual' => false,
            'permite_existencias' => true,
            'activo' => true,
        ]);

        $categoriaAlimentos = CategoriaProducto::query()->create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Alimentos Preparados',
            'activo' => true,
        ]);

        $categoriaInsumos = CategoriaProducto::query()->create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Materia Prima / Insumos',
            'activo' => true,
        ]);

        // Insumo 1: Carne molida de res (se maneja en kg)
        $this->insumoCarne = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $categoriaInsumos->id,
            'sku' => 'INS-CARNE-KG',
            'nombre' => 'Carne Molida de Res',
            'unidad_medida' => 'kg',
            'costo_referencia' => 160.00,
            'precio_base' => 0.00,
            'es_receta' => false,
            'activo' => true,
        ]);

        // Insumo 2: Pan Brioche (se maneja en piezas)
        $this->insumoPan = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $categoriaInsumos->id,
            'sku' => 'INS-PAN-BRIOCHE',
            'nombre' => 'Pan Brioche Artesanal',
            'unidad_medida' => 'pieza',
            'costo_referencia' => 12.00,
            'precio_base' => 0.00,
            'es_receta' => false,
            'activo' => true,
        ]);

        // Platillo: Hamburguesa Clásica (Producto elaborado con receta)
        $this->platilloHamburguesa = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $categoriaAlimentos->id,
            'sku' => 'PLAT-HAMB-01',
            'nombre' => 'Hamburguesa Clásica con Queso',
            'unidad_medida' => 'orden',
            'costo_referencia' => 45.00,
            'precio_base' => 165.00,
            'es_receta' => true,
            'activo' => true,
        ]);

        // Ficha técnica / Receta de la hamburguesa
        $receta = ProductoReceta::query()->create([
            'empresa_id' => $this->empresa->id,
            'producto_id' => $this->platilloHamburguesa->id,
            'rendimiento_porciones' => 1.00,
            'tiempo_preparacion_minutos' => 12,
            'estacion_cocina' => 'cocina',
            'activo' => true,
        ]);

        // Detalle de insumos:
        // 1 hamburguesa requiere 0.200 kg de carne (con 5% de merma) y 1 pieza de pan (sin merma)
        ProductoRecetaDetalle::query()->create([
            'producto_receta_id' => $receta->id,
            'insumo_producto_id' => $this->insumoCarne->id,
            'cantidad_requerida' => 0.2000,
            'porcentaje_merma' => 5.00, // 0.200 * 1.05 = 0.210 kg por hamburguesa
        ]);

        ProductoRecetaDetalle::query()->create([
            'producto_receta_id' => $receta->id,
            'insumo_producto_id' => $this->insumoPan->id,
            'cantidad_requerida' => 1.0000,
            'porcentaje_merma' => 0.00,
        ]);

        // Stock inicial en cocina: 10 kg de carne y 30 panes
        ExistenciaAlmacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->insumoCarne->id,
            'cantidad' => 10.0000,
            'cantidad_reservada' => 0.0000,
            'costo_promedio' => 160.0000,
        ]);

        ExistenciaAlmacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->insumoPan->id,
            'cantidad' => 30.0000,
            'cantidad_reservada' => 0.0000,
            'costo_promedio' => 12.0000,
        ]);

        $this->cajero = User::factory()->create();
        $this->cajero->assignRole('administrador');
    }

    #[Test]
    public function venta_de_producto_con_receta_descuenta_exactamente_los_insumos_del_almacen(): void
    {
        /** @var RegistrarVentaService $service */
        $service = app(RegistrarVentaService::class);

        // Venta de 2 hamburguesas
        $venta = $service->ejecutar([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'almacen_id' => $this->almacen->id,
            'user_id' => $this->cajero->id,
            'cliente_nombre' => 'Mesa 4',
            'forma_pago_sat' => '01',
            'lineas' => [
                [
                    'producto_id' => $this->platilloHamburguesa->id,
                    'cantidad' => 2.0000,
                    'precio_unitario' => 165.00,
                    'tasa_iva' => 0.1600,
                ],
            ],
            'pagos' => [
                [
                    'forma_pago_sat' => '01',
                    'monto' => 330.00,
                    'monto_recibido' => 350.00,
                    'cambio_devuelto' => 20.00,
                ],
            ],
        ]);

        $this->assertNotNull($venta->id);
        $this->assertSame('completada', $venta->estado);

        // Verificación de stock de carne:
        // Inicial: 10 kg
        // Consumo: 2 hamburguesas * (0.200 kg * 1.05 merma) = 0.420 kg
        // Restante esperado: 9.580 kg
        $existenciaCarne = ExistenciaAlmacen::query()
            ->where('almacen_id', $this->almacen->id)
            ->where('producto_id', $this->insumoCarne->id)
            ->first();

        $this->assertNotNull($existenciaCarne);
        $this->assertEquals(9.5800, (float) $existenciaCarne->cantidad);

        // Verificación de stock de pan:
        // Inicial: 30 pzas - (2 * 1) = 28 pzas
        $existenciaPan = ExistenciaAlmacen::query()
            ->where('almacen_id', $this->almacen->id)
            ->where('producto_id', $this->insumoPan->id)
            ->first();

        $this->assertNotNull($existenciaPan);
        $this->assertEquals(28.0000, (float) $existenciaPan->cantidad);

        // Verificación de movimientos de Kardex para los insumos
        $movimientos = MovimientoInventario::query()
            ->where('almacen_id', $this->almacen->id)
            ->where('referencia_id', $venta->id)
            ->get();

        $this->assertCount(2, $movimientos);

        $movCarne = $movimientos->firstWhere('producto_id', $this->insumoCarne->id);
        $this->assertNotNull($movCarne);
        $this->assertSame(MovimientoInventario::TIPO_SALIDA, $movCarne->tipo);
        $this->assertEquals(0.4200, (float) $movCarne->cantidad);
        $this->assertEquals(9.5800, (float) $movCarne->existencia_nueva);

        $movPan = $movimientos->firstWhere('producto_id', $this->insumoPan->id);
        $this->assertNotNull($movPan);
        $this->assertEquals(2.0000, (float) $movPan->cantidad);
        $this->assertEquals(28.0000, (float) $movPan->existencia_nueva);
    }
}
