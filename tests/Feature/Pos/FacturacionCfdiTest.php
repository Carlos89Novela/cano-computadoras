<?php

namespace Tests\Feature\Pos;

use App\Jobs\Facturacion\TimbrarVentaJob;
use App\Models\Almacen;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\EmpresaConfiguracionFiscal;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\Facturacion\Contracts\PacConnectorInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FacturacionCfdiTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Sucursal $sucursal;

    private Almacen $almacen;

    private Producto $producto;

    private User $admin;

    private EmpresaConfiguracionFiscal $configFiscal;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->empresa = Empresa::query()->create([
            'nombre' => 'Cano Computadoras Test',
            'activo' => true,
        ]);

        $this->configFiscal = EmpresaConfiguracionFiscal::query()->create([
            'empresa_id' => $this->empresa->id,
            'rfc' => 'XAXX010101000',
            'razon_social' => 'CANO COMPUTADORAS SA DE CV',
            'regimen_fiscal' => '601',
            'codigo_postal' => '83000',
            'facturacion_activa' => true,
            'timbres_contratados' => 100,
            'timbres_utilizados' => 0,
            'timbres_restantes' => 100,
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
            'codigo' => 'ALM-01',
            'nombre' => 'Almacén Principal',
            'tipo' => Almacen::TIPO_PRINCIPAL,
            'es_virtual' => false,
            'permite_existencias' => true,
            'activo' => true,
        ]);

        $categoria = CategoriaProducto::query()->create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Hardware',
            'activo' => true,
        ]);

        $this->producto = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $categoria->id,
            'sku' => 'MEM-16GB',
            'nombre' => 'Memoria RAM DDR4 16GB',
            'unidad_medida' => 'pieza',
            'costo_referencia' => 800.00,
            'precio_base' => 1160.00,
            'activo' => true,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
    }

    private function crearVentaPrueba(string $folio = 'V-TEST-001', string $estadoFacturacion = 'no_facturada'): Venta
    {
        /** @var Venta $venta */
        $venta = Venta::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'almacen_id' => $this->almacen->id,
            'user_id' => $this->admin->id,
            'folio' => $folio,
            'subtotal_sin_impuestos' => 1000.00,
            'base_imponible' => 1000.00,
            'descuento_total' => 0.00,
            'iva_total' => 160.00,
            'total' => 1160.00,
            'estado' => 'completada',
            'estado_facturacion' => $estadoFacturacion,
            'forma_pago_sat' => '01',
            'metodo_pago_sat' => 'PUE',
            'cliente_rfc' => 'XAXX010101000',
            'cliente_nombre' => 'PUBLICO EN GENERAL',
        ]);

        VentaDetalle::query()->create([
            'venta_id' => $venta->id,
            'producto_id' => $this->producto->id,
            'almacen_id' => $this->almacen->id,
            'sku' => $this->producto->sku,
            'descripcion_producto' => $this->producto->nombre,
            'cantidad' => 1.0000,
            'precio_unitario' => 1000.00,
            'subtotal' => 1000.00,
            'tasa_iva' => 0.16,
            'monto_iva_unitario' => 160.00,
            'total' => 1160.00,
            'clave_sat_producto' => '43211500',
            'clave_sat_unidad' => 'H87',
        ]);

        return $venta;
    }

    #[Test]
    public function venta_se_timbra_exitosamente_y_genera_archivo_xml(): void
    {
        $venta = $this->crearVentaPrueba('V-001');

        $response = $this->actingAs($this->admin)->postJson(
            route('facturacion.ventas.timbrar', $venta)
        );

        $response->assertOk();
        $response->assertJsonStructure(['message', 'uuid', 'folio', 'estado_facturacion']);

        $venta->refresh();
        $this->assertSame('facturada', $venta->estado_facturacion);
        $this->assertNotNull($venta->uuid_fiscal);
        $this->assertNotNull($venta->xml_path);
        Storage::disk('local')->assertExists((string) $venta->xml_path);

        $this->configFiscal->refresh();
        $this->assertEquals(1, $this->configFiscal->timbres_utilizados);
        $this->assertEquals(99, $this->configFiscal->timbres_restantes);
    }

    #[Test]
    public function si_el_pac_falla_la_venta_queda_pendiente_y_se_encola_el_reintento(): void
    {
        Queue::fake();

        // Simular conector PAC que falla temporalmente
        $mockFalla = new class implements PacConnectorInterface
        {
            public function timbrarXml(string $xmlSinTimbrar, EmpresaConfiguracionFiscal $configFiscal): array
            {
                return [
                    'exito' => false,
                    'mensaje_error' => 'Servicio PAC en mantenimiento temporal.',
                ];
            }

            public function cancelarCfdi(string $uuid, string $motivo, ?string $folioSustitucion, EmpresaConfiguracionFiscal $configFiscal): array
            {
                return ['exito' => true];
            }
        };

        $this->app->instance(PacConnectorInterface::class, $mockFalla);

        $venta = $this->crearVentaPrueba('V-FALLA-001');

        $response = $this->actingAs($this->admin)->postJson(
            route('facturacion.ventas.timbrar', $venta)
        );

        $response->assertStatus(202);
        $response->assertJsonFragment(['estado_facturacion' => 'pendiente']);

        $venta->refresh();
        $this->assertSame('pendiente', $venta->estado_facturacion);

        Queue::assertPushed(TimbrarVentaJob::class, function (TimbrarVentaJob $job) use ($venta) {
            return $job->venta->id === $venta->id;
        });
    }

    #[Test]
    public function comando_procesa_todas_las_facturas_acumuladas_en_lote(): void
    {
        $venta1 = $this->crearVentaPrueba('V-PEND-01', 'pendiente');
        $venta2 = $this->crearVentaPrueba('V-PEND-02', 'pendiente');

        $this->artisan('facturacion:procesar-pendientes', ['--empresa_id' => $this->empresa->id])
            ->assertSuccessful()
            ->expectsOutputToContain('2 facturas pendientes')
            ->expectsOutputToContain('2 timbradas, 0 pendientes');

        $venta1->refresh();
        $venta2->refresh();

        $this->assertSame('facturada', $venta1->estado_facturacion);
        $this->assertSame('facturada', $venta2->estado_facturacion);
        $this->assertNotNull($venta1->uuid_fiscal);
        $this->assertNotNull($venta2->uuid_fiscal);
    }
}
