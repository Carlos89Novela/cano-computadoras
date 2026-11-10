<?php

namespace Tests\Feature\Pos;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\EmpresaConfiguracionFiscal;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FacturaGlobalCfdiTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Sucursal $sucursal;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->empresa = Empresa::query()->create([
            'nombre' => 'Cano POS Factura Global',
            'giro_comercial' => 'computadoras_tecnologia',
            'plan_suscripcion' => 'avanzado',
            'activo' => true,
        ]);

        EmpresaConfiguracionFiscal::query()->create([
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
            'codigo' => 'SUC-GLO',
            'nombre' => 'Sucursal Matriz',
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

        $this->administrador = User::factory()->create();
        $this->administrador->assignRole('administrador');
        $this->sucursal->usuarios()->attach($this->administrador->id, [
            'es_gerente' => true,
            'es_principal' => true,
            'activo' => true,
        ]);
    }

    #[Test]
    public function consolida_ventas_del_dia_en_un_cfdi_global_4_y_las_asocia(): void
    {
        // 1. Crear 2 ventas de mostrador completadas
        $v1 = new Venta();
        $v1->forceFill([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'almacen_id' => 1,
            'user_id' => $this->administrador->id,
            'folio' => 'VTA-GLO-001',
            'estado' => 'completada',
            'estado_facturacion' => 'no_facturada',
            'base_imponible' => 100.00,
            'iva_total' => 16.00,
            'total' => 116.00,
        ]);
        $v1->save();

        $v2 = new Venta();
        $v2->forceFill([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'almacen_id' => 1,
            'user_id' => $this->administrador->id,
            'folio' => 'VTA-GLO-002',
            'estado' => 'completada',
            'estado_facturacion' => 'no_facturada',
            'base_imponible' => 200.00,
            'iva_total' => 32.00,
            'total' => 232.00,
        ]);
        $v2->save();

        // 2. Procesar factura global diaria del mes actual
        $response = $this->actingAs($this->administrador)->postJson(route('facturacion.global.procesar'), [
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'periodicidad' => '01', // Diario
            'meses' => now()->format('m'),
            'anio' => (int) now()->format('Y'),
            'forma_pago_predeterminada' => '01',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['message', 'uuid_sat', 'folio', 'ventas_consolidadas', 'total']);
        $this->assertEquals(2, $response->json('ventas_consolidadas'));
        $this->assertEquals(348.00, $response->json('total'));

        // 3. Verificar en BD la venta global timbrada
        /** @var Venta|null $ventaGlobal */
        $ventaGlobal = Venta::query()->where('es_factura_global', true)->first();
        $this->assertNotNull($ventaGlobal);
        $this->assertSame('facturada', $ventaGlobal->estado_facturacion);
        $this->assertSame('XAXX010101000', $ventaGlobal->cliente_rfc);
        $this->assertSame('PUBLICO EN GENERAL', $ventaGlobal->cliente_nombre);
        $this->assertEquals(348.00, $ventaGlobal->total);
        $this->assertNotNull($ventaGlobal->uuid_fiscal);
        Storage::disk('local')->assertExists((string) $ventaGlobal->xml_path);

        // 4. Verificar que ambas ventas quedaron ligadas a esta factura global
        $v1->refresh();
        $v2->refresh();
        $this->assertEquals($ventaGlobal->uuid_fiscal, $v1->uuid_factura_global);
        $this->assertEquals($ventaGlobal->uuid_fiscal, $v2->uuid_factura_global);
    }
}
