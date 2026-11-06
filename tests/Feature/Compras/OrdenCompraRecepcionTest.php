<?php

namespace Tests\Feature\Compras;

use App\Actions\Inventario\RegistrarRecepcionInventario;
use App\Models\Almacen;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\ExistenciaAlmacen;
use App\Models\MovimientoInventario;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\RecepcionInventario;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrdenCompraRecepcionTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Sucursal $sucursal;

    private Almacen $almacen;

    private Proveedor $proveedor;

    private User $actor;

    private Producto $productoA;

    private Producto $productoB;

    protected function setUp(): void
    {
        parent::setUp();

        // Permisos necesarios
        Permission::findOrCreate('inventario.registrar_entrada');
        Permission::findOrCreate('compras.autorizar');

        $this->actor = User::factory()->create();
        $this->actor->givePermissionTo(['inventario.registrar_entrada', 'compras.autorizar']);

        $this->empresa = Empresa::query()->create([
            'nombre' => 'Cano Computadoras Test '.Str::random(5),
            'razon_social' => 'Cano Computadoras S.A. de C.V.',
            'rfc' => 'CANO900101XYZ',
            'activo' => true,
            'creado_por_id' => $this->actor->id,
        ]);

        $this->sucursal = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-MATRIZ-'.Str::upper(Str::random(4)),
            'nombre' => 'Sucursal Matriz',
            'activo' => true,
            'es_principal' => true,
            'creado_por_id' => $this->actor->id,
        ]);

        $this->almacen = Almacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'codigo' => 'ALM-MATRIZ-'.Str::upper(Str::random(4)),
            'nombre' => 'Almacén General Matriz',
            'tipo' => Almacen::TIPO_PRINCIPAL,
            'es_virtual' => false,
            'permite_existencias' => true,
            'activo' => true,
            'creado_por_id' => $this->actor->id,
        ]);

        $this->proveedor = Proveedor::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'PROV-'.Str::upper(Str::random(4)),
            'nombre' => 'Mayorista Tecnológico S.A.',
            'razon_social' => 'Mayorista Tecnológico S.A. de C.V.',
            'rfc' => 'MTE900101XYZ',
            'activo' => true,
            'creado_por_id' => $this->actor->id,
        ]);

        $categoria = CategoriaProducto::query()->create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Almacenamiento '.Str::random(4),
            'activo' => true,
            'creado_por_id' => $this->actor->id,
        ]);

        $this->productoA = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $categoria->id,
            'sku' => 'SSD-1TB-'.Str::upper(Str::random(4)),
            'nombre' => 'Disco SSD NVMe 1TB',
            'costo_referencia' => 100.00,
            'precio_base' => 150.00,
            'activo' => true,
            'creado_por_id' => $this->actor->id,
        ]);

        $this->productoB = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $categoria->id,
            'sku' => 'RAM-16GB-'.Str::upper(Str::random(4)),
            'nombre' => 'Memoria RAM 16GB',
            'costo_referencia' => 50.00,
            'precio_base' => 80.00,
            'activo' => true,
            'creado_por_id' => $this->actor->id,
        ]);
    }

    #[Test]
    public function recepcion_parcial_actualiza_orden_a_recibida_parcial_y_recalcula_cpp(): void
    {
        // 1. Estado previo: 10 piezas de Producto A a $100.0000
        ExistenciaAlmacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->productoA->id,
            'cantidad' => '10.0000',
            'cantidad_reservada' => '0.0000',
            'costo_promedio' => '100.0000',
            'actualizado_por_id' => $this->actor->id,
        ]);

        // 2. Orden de compra autorizada: 10 pzas Prod A ($130) y 5 pzas Prod B ($50)
        $orden = OrdenCompra::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'almacen_id' => $this->almacen->id,
            'proveedor_id' => $this->proveedor->id,
            'user_id' => $this->actor->id,
            'autorizado_por_id' => $this->actor->id,
            'folio' => 'OC-TEST-0001',
            'estado' => 'autorizada',
            'fecha_emision' => now()->toDateString(),
            'autorizada_at' => now(),
            'subtotal' => 1550.00,
            'iva_total' => 248.00,
            'total' => 1798.00,
        ]);

        $detalleA = OrdenCompraDetalle::query()->create([
            'orden_compra_id' => $orden->id,
            'producto_id' => $this->productoA->id,
            'cantidad' => '10.0000',
            'cantidad_recibida' => '0.0000',
            'costo_unitario' => '130.0000',
            'subtotal' => '1300.00',
            'total' => '1508.00',
        ]);

        $detalleB = OrdenCompraDetalle::query()->create([
            'orden_compra_id' => $orden->id,
            'producto_id' => $this->productoB->id,
            'cantidad' => '5.0000',
            'cantidad_recibida' => '0.0000',
            'costo_unitario' => '50.0000',
            'subtotal' => '250.00',
            'total' => '290.00',
        ]);

        // 3. Recepción parcial: solo recibimos 5 piezas del Producto A a $130
        // CPP Prod A esperado: ((10 * 100) + (5 * 130)) / 15 = (1000 + 650) / 15 = 110.0000
        $datos = [
            'orden_compra_id' => $orden->id,
            'tipo' => RecepcionInventario::TIPO_COMPRA,
            'referencia_externa' => 'FAC-PARCIAL-01',
            'motivo' => 'Entrega parcial de proveedor',
            'lineas' => [
                [
                    'producto_id' => $this->productoA->id,
                    'cantidad' => 5,
                    'costo_unitario' => 130,
                ],
            ],
        ];

        /** @var RegistrarRecepcionInventario $action */
        $action = app(RegistrarRecepcionInventario::class);
        $recepcion = $action->ejecutar(
            empresa: $this->empresa,
            almacen: $this->almacen,
            proveedor: $this->proveedor,
            actor: $this->actor,
            datos: $datos
        );

        // Validaciones en BD
        $this->assertDatabaseHas('recepciones_inventario', [
            'id' => $recepcion->id,
            'orden_compra_id' => $orden->id,
            'total_lineas' => 1,
            'importe_total' => '650.0000',
        ]);

        // Estado de la orden debe ser recibida_parcial
        $orden->refresh();
        $this->assertEquals('recibida_parcial', $orden->estado);

        $detalleA->refresh();
        $this->assertEquals('5.0000', $detalleA->cantidad_recibida);

        // Existencia y recálculo de CPP
        $existenciaA = ExistenciaAlmacen::query()
            ->where('almacen_id', $this->almacen->id)
            ->where('producto_id', $this->productoA->id)
            ->firstOrFail();

        $this->assertEquals('15.0000', $existenciaA->cantidad);
        $this->assertEquals('110.0000', $existenciaA->costo_promedio);

        // Movimiento de Kardex
        $this->assertDatabaseHas('movimientos_inventario', [
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->productoA->id,
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'referencia_tipo' => RecepcionInventario::class,
            'referencia_id' => $recepcion->id,
            'cantidad' => '5.0000',
        ]);
    }

    #[Test]
    public function recepcion_completa_actualiza_orden_a_recibida_total(): void
    {
        $orden = OrdenCompra::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'almacen_id' => $this->almacen->id,
            'proveedor_id' => $this->proveedor->id,
            'user_id' => $this->actor->id,
            'autorizado_por_id' => $this->actor->id,
            'folio' => 'OC-TEST-0002',
            'estado' => 'autorizada',
            'fecha_emision' => now()->toDateString(),
            'subtotal' => 250.00,
            'iva_total' => 40.00,
            'total' => 290.00,
        ]);

        OrdenCompraDetalle::query()->create([
            'orden_compra_id' => $orden->id,
            'producto_id' => $this->productoB->id,
            'cantidad' => '5.0000',
            'cantidad_recibida' => '0.0000',
            'costo_unitario' => '50.0000',
            'subtotal' => '250.00',
            'total' => '290.00',
        ]);

        $datos = [
            'orden_compra_id' => $orden->id,
            'tipo' => RecepcionInventario::TIPO_COMPRA,
            'referencia_externa' => 'FAC-TOTAL-01',
            'motivo' => 'Entrega total',
            'lineas' => [
                [
                    'producto_id' => $this->productoB->id,
                    'cantidad' => 5,
                    'costo_unitario' => 50,
                ],
            ],
        ];

        /** @var RegistrarRecepcionInventario $action */
        $action = app(RegistrarRecepcionInventario::class);
        $action->ejecutar(
            empresa: $this->empresa,
            almacen: $this->almacen,
            proveedor: $this->proveedor,
            actor: $this->actor,
            datos: $datos
        );

        $orden->refresh();
        $this->assertEquals('recibida_total', $orden->estado);

        $existenciaB = ExistenciaAlmacen::query()
            ->where('almacen_id', $this->almacen->id)
            ->where('producto_id', $this->productoB->id)
            ->firstOrFail();

        $this->assertEquals('5.0000', $existenciaB->cantidad);
        $this->assertEquals('50.0000', $existenciaB->costo_promedio);
    }
}
