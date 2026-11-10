<?php

namespace Tests\Feature\Restaurante;

use App\Models\Almacen;
use App\Models\CajaTurno;
use App\Models\CategoriaProducto;
use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\Empresa;
use App\Models\EmpresaConfiguracionFiscal;
use App\Models\ExistenciaAlmacen;
use App\Models\Producto;
use App\Models\RestauranteArea;
use App\Models\RestauranteMesa;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DividirCuentaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Sucursal $sucursal;

    private User $cajero;

    private RestauranteMesa $mesa;

    private Comanda $comanda;

    private ComandaItem $item1;

    private ComandaItem $item2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->empresa = Empresa::query()->create([
            'nombre' => 'Restaurante Split Test',
            'giro_comercial' => 'restaurante_cafeteria',
            'plan_suscripcion' => 'avanzado',
            'activo' => true,
        ]);

        EmpresaConfiguracionFiscal::query()->create([
            'empresa_id' => $this->empresa->id,
            'rfc' => 'XAXX010101000',
            'razon_social' => 'RESTAURANTE SPLIT TEST',
            'regimen_fiscal' => '601',
            'codigo_postal' => '83000',
            'facturacion_activa' => true,
            'timbres_contratados' => 100,
            'timbres_utilizados' => 0,
            'timbres_restantes' => 100,
        ]);

        $this->sucursal = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-SPLIT',
            'nombre' => 'Sucursal Split',
            'activo' => true,
        ]);

        Almacen::query()->create([
            'id' => 1,
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'codigo' => 'ALM-1',
            'nombre' => 'Almacén 1',
            'tipo' => Almacen::TIPO_PRINCIPAL,
            'es_virtual' => false,
            'permite_existencias' => true,
            'activo' => true,
        ]);

        $this->cajero = User::factory()->create();
        $this->cajero->assignRole('administrador');
        $this->sucursal->usuarios()->attach($this->cajero->id, [
            'es_gerente' => true,
            'es_principal' => true,
            'activo' => true,
        ]);

        CajaTurno::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'user_id' => $this->cajero->id,
            'apertura_at' => now(),
            'monto_inicial' => 1000.00,
            'monto_esperado_efectivo' => 1000.00,
            'estado' => 'abierto',
        ]);

        $area = RestauranteArea::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Salón',
            'orden' => 1,
            'activo' => true,
        ]);

        $this->mesa = RestauranteMesa::query()->create([
            'restaurante_area_id' => $area->id,
            'numero_o_nombre' => 'Mesa 3',
            'capacidad' => 4,
            'forma' => 'cuadrada',
            'posicion_x' => 20.00,
            'posicion_y' => 30.00,
            'estado' => 'ocupada',
        ]);

        $categoria = CategoriaProducto::query()->create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Alimentos',
            'activo' => true,
        ]);

        $prodPizza = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $categoria->id,
            'sku' => 'PLAT-PIZZA',
            'nombre' => 'Pizza Artesanal',
            'unidad_medida' => 'pieza',
            'costo_referencia' => 80.00,
            'precio_base' => 220.00,
            'es_receta' => false,
            'activo' => true,
        ]);

        $prodCerveza = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $categoria->id,
            'sku' => 'BEB-CERV',
            'nombre' => 'Cerveza Artesanal',
            'unidad_medida' => 'pieza',
            'costo_referencia' => 30.00,
            'precio_base' => 85.00,
            'es_receta' => false,
            'activo' => true,
        ]);

        ExistenciaAlmacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'almacen_id' => 1,
            'producto_id' => $prodPizza->id,
            'cantidad' => 10.00,
            'cantidad_reservada' => 0.00,
            'costo_promedio' => 80.00,
        ]);

        ExistenciaAlmacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'almacen_id' => 1,
            'producto_id' => $prodCerveza->id,
            'cantidad' => 20.00,
            'cantidad_reservada' => 0.00,
            'costo_promedio' => 30.00,
        ]);

        $this->comanda = Comanda::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'mesero_user_id' => $this->cajero->id,
            'folio' => 'CMD-SPLIT-001',
            'mesa_o_referencia' => 'Mesa 3',
            'estado' => 'en_preparacion',
            'enviada_a_cocina_at' => now(),
        ]);

        $this->item1 = ComandaItem::query()->create([
            'comanda_id' => $this->comanda->id,
            'producto_id' => $prodPizza->id,
            'cantidad' => 1.00,
            'estacion_cocina' => 'cocina',
            'estado' => 'listo',
        ]);

        $this->item2 = ComandaItem::query()->create([
            'comanda_id' => $this->comanda->id,
            'producto_id' => $prodCerveza->id,
            'cantidad' => 2.00,
            'estacion_cocina' => 'barra',
            'estado' => 'listo',
        ]);

        $this->mesa->update(['comanda_actual_id' => $this->comanda->id]);
    }

    #[Test]
    public function mesa_puede_dividir_cuenta_por_platillos_y_libera_solo_al_terminar(): void
    {
        // 1. Comensal 1 paga solo la pizza ($220)
        $resPago1 = $this->actingAs($this->cajero)->postJson(
            route('restaurante.mesas.dividir-cuenta', $this->mesa),
            [
                'forma_pago_sat' => '01',
                'modo' => 'items',
                'items' => [
                    [
                        'comanda_item_id' => $this->item1->id,
                        'cantidad' => 1.00,
                    ],
                ],
            ]
        );

        $resPago1->assertOk();
        $this->assertFalse($resPago1->json('comanda_cerrada'));

        // La mesa sigue ocupada porque quedan las cervezas
        $this->mesa->refresh();
        $this->assertSame('ocupada', $this->mesa->estado);
        $this->assertNotNull($this->mesa->comanda_actual_id);

        // 2. Comensal 2 paga las 2 cervezas ($170)
        $resPago2 = $this->actingAs($this->cajero)->postJson(
            route('restaurante.mesas.dividir-cuenta', $this->mesa),
            [
                'forma_pago_sat' => '04', // Tarjeta de crédito
                'modo' => 'items',
                'items' => [
                    [
                        'comanda_item_id' => $this->item2->id,
                        'cantidad' => 2.00,
                    ],
                ],
            ]
        );

        $resPago2->assertOk();
        $this->assertTrue($resPago2->json('comanda_cerrada'));

        // Ahora que se liquidó todo, la mesa queda liberada
        $this->mesa->refresh();
        $this->assertSame('disponible', $this->mesa->estado);
        $this->assertNull($this->mesa->comanda_actual_id);

        // Y la comanda queda cerrada
        $this->comanda->refresh();
        $this->assertSame('completada', $this->comanda->estado);
    }
}
