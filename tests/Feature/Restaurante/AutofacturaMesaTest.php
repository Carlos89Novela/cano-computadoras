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

class AutofacturaMesaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Sucursal $sucursal;

    private User $cajero;

    private RestauranteArea $area;

    private RestauranteMesa $mesa;

    private Comanda $comanda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->empresa = Empresa::query()->create([
            'nombre' => 'Restaurante Fiscal Test',
            'giro_comercial' => 'restaurante_cafeteria',
            'plan_suscripcion' => 'avanzado',
            'activo' => true,
        ]);

        EmpresaConfiguracionFiscal::query()->create([
            'empresa_id' => $this->empresa->id,
            'rfc' => 'XAXX010101000',
            'razon_social' => 'RESTAURANTE FISCAL TEST',
            'regimen_fiscal' => '601',
            'codigo_postal' => '83000',
            'facturacion_activa' => true,
            'timbres_contratados' => 100,
            'timbres_utilizados' => 0,
            'timbres_restantes' => 100,
        ]);

        $this->sucursal = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-FISC',
            'nombre' => 'Sucursal Restaurante Fiscal',
            'activo' => true,
        ]);

        Almacen::query()->create([
            'id' => 1,
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'codigo' => 'ALM-1',
            'nombre' => 'Almacén Principal',
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

        $this->area = RestauranteArea::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Salón Terraza',
            'orden' => 1,
            'activo' => true,
        ]);

        $this->mesa = RestauranteMesa::query()->create([
            'restaurante_area_id' => $this->area->id,
            'numero_o_nombre' => 'Mesa 8',
            'capacidad' => 4,
            'forma' => 'redonda',
            'posicion_x' => 30.00,
            'posicion_y' => 40.00,
            'estado' => 'ocupada',
        ]);

        $categoria = CategoriaProducto::query()->create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Comida y Bebidas',
            'activo' => true,
        ]);

        $platillo = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $categoria->id,
            'sku' => 'PLAT-CORTE-01',
            'nombre' => 'Corte Ribeye 400g',
            'unidad_medida' => 'orden',
            'costo_referencia' => 120.00,
            'precio_base' => 380.00,
            'es_receta' => false,
            'activo' => true,
        ]);

        // Stock inicial en almacén para permitir la venta
        ExistenciaAlmacen::query()->create([
            'empresa_id' => $this->empresa->id,
            'almacen_id' => 1,
            'producto_id' => $platillo->id,
            'cantidad' => 20.00,
            'cantidad_reservada' => 0.00,
            'costo_promedio' => 120.00,
        ]);

        $this->comanda = Comanda::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'mesero_user_id' => $this->cajero->id,
            'folio' => 'CMD-SUC-FISC-20261110-001',
            'mesa_o_referencia' => 'Mesa 8',
            'estado' => 'en_preparacion',
            'enviada_a_cocina_at' => now(),
        ]);

        ComandaItem::query()->create([
            'comanda_id' => $this->comanda->id,
            'producto_id' => $platillo->id,
            'cantidad' => 2.00,
            'estacion_cocina' => 'cocina',
            'estado' => 'listo',
        ]);

        $this->mesa->update(['comanda_actual_id' => $this->comanda->id]);
    }

    #[Test]
    public function flujo_completo_precuenta_liquidacion_y_autofacturacion_sat(): void
    {
        // 1. Ver la precuenta térmica
        $resPrecuenta = $this->actingAs($this->cajero)->get(
            route('restaurante.mesas.precuenta', $this->mesa)
        );
        $resPrecuenta->assertOk();
        $resPrecuenta->assertSee('ESTADO DE CUENTA (PRECUENTA)');
        $resPrecuenta->assertSee('Mesa 8');
        $resPrecuenta->assertSee('$760.00');

        // 2. Liquidar la mesa desde el POS
        $resLiquidar = $this->actingAs($this->cajero)->postJson(
            route('restaurante.mesas.liquidar', $this->mesa),
            [
                'forma_pago_sat' => '01',
                'monto_recibido' => 800.00,
                'propina' => 50.00,
            ]
        );

        $resLiquidar->assertOk();
        $resLiquidar->assertJsonStructure(['venta_folio', 'token_autofactura']);

        $tokenAutofactura = (string) $resLiquidar->json('token_autofactura');

        // Comprobar que la mesa quedó libre
        $this->mesa->refresh();
        $this->assertSame('disponible', $this->mesa->estado);
        $this->assertNull($this->mesa->comanda_actual_id);

        // Comprobar comanda finalizada
        $this->comanda->refresh();
        $this->assertSame('completada', $this->comanda->estado);

        // 3. Acceso público al portal de autofacturación mediante token del ticket
        $resPortal = $this->get(route('autofactura.show', $tokenAutofactura));
        $resPortal->assertOk();
        $resPortal->assertSee('Portal de Emisión de CFDI 4.0');

        // 4. Timbrado en tiempo real solicitado por el comensal
        $resTimbrado = $this->postJson(route('autofactura.procesar', $tokenAutofactura), [
            'rfc' => 'CACX850101XXX',
            'razon_social' => 'CLIENTE RESTAURANTE PRUEBA',
            'codigo_postal' => '83000',
            'regimen_fiscal' => '612',
            'uso_cfdi' => 'G03',
            'correo' => 'cliente@correo.com',
        ]);

        $resTimbrado->assertOk();
        $resTimbrado->assertJsonStructure(['uuid_fiscal', 'descargar_xml', 'descargar_pdf']);
    }
}
