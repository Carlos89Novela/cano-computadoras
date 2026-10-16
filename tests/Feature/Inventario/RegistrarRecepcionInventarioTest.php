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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RegistrarRecepcionInventarioTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Sucursal $sucursal;
    private Almacen $almacen;
    private Proveedor $proveedor;
    private CategoriaProducto $categoria;
    private User $actor;
    private Producto $productoA;
    private Producto $productoB;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Permiso requerido
        Permission::findOrCreate('inventario.registrar_entrada');

        // 2. Usuario con permiso
        $this->actor = User::factory()->create();
        $this->actor->givePermissionTo('inventario.registrar_entrada');

        // 3. Empresa base
        $this->empresa = Empresa::query()->create([
            'nombre' => 'Cano Computadoras Test ' . Str::random(5),
            'razon_social' => 'Cano Computadoras S.A. de C.V.',
            'rfc' => 'CANO900101XYZ',
            'activo' => true,
            'creado_por_id' => $this->actor->id,
        ]);

        // 4. Sucursal base
        $this->sucursal = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-CENTRO-' . Str::upper(Str::random(4)),
            'nombre' => 'Sucursal Centro',
            'activo' => true,
            'es_principal' => true,
            'creado_por_id' => $this->actor->id,
        ]);

        // 5. Almacén base (físico, activo y permite existencias)
        $this->almacen = Almacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'codigo' => 'ALM-GRAL-' . Str::upper(Str::random(4)),
            'nombre' => 'Almacén General',
            'tipo' => Almacen::TIPO_PRINCIPAL,
            'es_virtual' => false,
            'permite_existencias' => true,
            'activo' => true,
            'creado_por_id' => $this->actor->id,
        ]);

        // 6. Proveedor base
        $this->proveedor = Proveedor::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'PROV-' . Str::upper(Str::random(4)),
            'nombre' => 'Proveedor Mayorista S.A.',
            'activo' => true,
            'creado_por_id' => $this->actor->id,
        ]);

        // 7. Categoría de productos requerida
        $this->categoria = CategoriaProducto::query()->create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Componentes y Refacciones ' . Str::random(4),
            'activo' => true,
            'creado_por_id' => $this->actor->id,
        ]);

        // 8. Productos de prueba con categoría asignada
        $this->productoA = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $this->categoria->id,
            'sku' => 'SKU-DISCO-SSD-' . Str::upper(Str::random(4)),
            'nombre' => 'Disco SSD NVMe 1TB',
            'costo_referencia' => 100.00,
            'precio_base' => 150.00,
            'activo' => true,
            'creado_por_id' => $this->actor->id,
        ]);

        $this->productoB = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $this->categoria->id,
            'sku' => 'SKU-RAM-16GB-' . Str::upper(Str::random(4)),
            'nombre' => 'Memoria RAM DDR4 16GB',
            'costo_referencia' => 50.00,
            'precio_base' => 80.00,
            'activo' => true,
            'creado_por_id' => $this->actor->id,
        ]);
    }

    #[Test]
    public function registra_recepcion_exitosa_calcula_costo_promedio_y_crea_movimientos(): void
    {
        // Estado previo: productoA ya tiene 10 piezas con costo promedio de $100.0000
        ExistenciaAlmacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->productoA->id,
            'cantidad' => '10.0000',
            'cantidad_reservada' => '0.0000',
            'costo_promedio' => '100.0000',
            'actualizado_por_id' => $this->actor->id,
        ]);

        // Entrada:
        // Producto A: 5 piezas a $130.0000
        // Cálculo esperado CPP: ((10 * 100) + (5 * 130)) / 15 = 1650 / 15 = 110.0000
        // Producto B: Primera entrada de 8 piezas a $50.0000 -> CPP = 50.0000
        $datos = [
            'tipo' => RecepcionInventario::TIPO_COMPRA,
            'referencia_externa' => 'FAC-2026-999',
            'motivo' => 'Compra mensual de refacciones y accesorios',
            'lineas' => [
                [
                    'producto_id' => $this->productoA->id,
                    'cantidad' => 5,
                    'costo_unitario' => 130,
                ],
                [
                    'producto_id' => $this->productoB->id,
                    'cantidad' => 8,
                    'costo_unitario' => 50,
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

        // Validar Encabezado
        $this->assertDatabaseHas('recepciones_inventario', [
            'id' => $recepcion->id,
            'empresa_id' => $this->empresa->id,
            'almacen_id' => $this->almacen->id,
            'proveedor_id' => $this->proveedor->id,
            'tipo' => 'compra',
            'total_lineas' => 2,
            'importe_total' => '1050.0000',
        ]);

        // Validar Existencias y CPP
        $existenciaA = ExistenciaAlmacen::query()
            ->where('almacen_id', $this->almacen->id)
            ->where('producto_id', $this->productoA->id)
            ->firstOrFail();
        $this->assertEquals('15.0000', $existenciaA->cantidad);
        $this->assertEquals('110.0000', $existenciaA->costo_promedio);

        $existenciaB = ExistenciaAlmacen::query()
            ->where('almacen_id', $this->almacen->id)
            ->where('producto_id', $this->productoB->id)
            ->firstOrFail();
        $this->assertEquals('8.0000', $existenciaB->cantidad);
        $this->assertEquals('50.0000', $existenciaB->costo_promedio);

        // Validar Kardex / MovimientoInventario
        $movimientos = MovimientoInventario::query()
            ->where('grupo_id', $recepcion->grupo_id)
            ->get();

        $this->assertCount(2, $movimientos);
        foreach ($movimientos as $movimiento) {
            $this->assertEquals(RecepcionInventario::class, $movimiento->referencia_tipo);
            $this->assertEquals($recepcion->id, $movimiento->referencia_id);
            $this->assertEquals(MovimientoInventario::TIPO_ENTRADA, $movimiento->tipo);
        }
    }

    #[Test]
    public function rechaza_si_el_usuario_no_tiene_el_permiso(): void
    {
        $usuarioSinPermiso = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        /** @var RegistrarRecepcionInventario $action */
        $action = app(RegistrarRecepcionInventario::class);
        $action->ejecutar(
            empresa: $this->empresa,
            almacen: $this->almacen,
            proveedor: $this->proveedor,
            actor: $usuarioSinPermiso,
            datos: [
                'tipo' => RecepcionInventario::TIPO_COMPRA,
                'motivo' => 'Prueba sin permiso',
                'lineas' => [
                    ['producto_id' => $this->productoA->id, 'cantidad' => 1, 'costo_unitario' => 10],
                ],
            ]
        );
    }

    #[Test]
    public function rechaza_si_se_intenta_duplicar_un_producto_en_la_misma_recepcion(): void
    {
        $this->expectException(ValidationException::class);

        /** @var RegistrarRecepcionInventario $action */
        $action = app(RegistrarRecepcionInventario::class);
        $action->ejecutar(
            empresa: $this->empresa,
            almacen: $this->almacen,
            proveedor: $this->proveedor,
            actor: $this->actor,
            datos: [
                'tipo' => RecepcionInventario::TIPO_COMPRA,
                'motivo' => 'Intento de duplicación',
                'lineas' => [
                    ['producto_id' => $this->productoA->id, 'cantidad' => 2, 'costo_unitario' => 50],
                    ['producto_id' => $this->productoA->id, 'cantidad' => 3, 'costo_unitario' => 50],
                ],
            ]
        );
    }

    #[Test]
    public function rechaza_si_el_almacen_no_permite_existencias(): void
    {
        $this->almacen->update(['permite_existencias' => false]);

        $this->expectException(ValidationException::class);

        /** @var RegistrarRecepcionInventario $action */
        $action = app(RegistrarRecepcionInventario::class);
        $action->ejecutar(
            empresa: $this->empresa,
            almacen: $this->almacen,
            proveedor: $this->proveedor,
            actor: $this->actor,
            datos: [
                'tipo' => RecepcionInventario::TIPO_RECEPCION,
                'motivo' => 'Intento en almacén sin existencias',
                'lineas' => [
                    ['producto_id' => $this->productoA->id, 'cantidad' => 2, 'costo_unitario' => 50],
                ],
            ]
        );
    }
}
