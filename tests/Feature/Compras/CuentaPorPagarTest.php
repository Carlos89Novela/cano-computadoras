<?php

namespace Tests\Feature\Compras;

use App\Models\CuentaPorPagar;
use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CuentaPorPagarTest extends TestCase
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
            'nombre' => 'Cano Computadoras CXP',
            'giro_comercial' => 'computadoras_tecnologia',
            'plan_suscripcion' => 'avanzado',
            'activo' => true,
        ]);

        $this->sucursal = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-CXP',
            'nombre' => 'Sucursal Compras',
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
    public function procesa_xml_de_proveedor_crea_la_deuda_y_aplica_pagos_parciales(): void
    {
        $uuidSimulado = 'A1B2C3D4-E5F6-7A8B-9C0D-1E2F3A4B5C6D';
        $xmlContenido = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="4.0" Serie="F" Folio="9981" Fecha="2026-11-10T10:00:00" SubTotal="1000.00" Total="1160.00">
    <cfdi:Emisor Rfc="PRO901231ABC" Nombre="MAYORISTA DE TECNOLOGIA SA DE CV" RegimenFiscal="601" />
    <cfdi:Receptor Rfc="XAXX010101000" Nombre="CANO COMPUTADORAS" UsoCFDI="G01" />
    <cfdi:Impuestos TotalImpuestosTrasladados="160.00" />
    <cfdi:Complemento>
        <tfd:TimbreFiscalDigital UUID="{$uuidSimulado}" FechaTimbrado="2026-11-10T10:05:00" />
    </cfdi:Complemento>
</cfdi:Comprobante>
XML;

        $archivoXml = UploadedFile::fake()->createWithContent('factura_proveedor.xml', $xmlContenido);

        // 1. Subir XML mediante endpoint
        $responseUpload = $this->actingAs($this->administrador)->postJson(route('compras.cxp.subir-xml'), [
            'xml_file' => $archivoXml,
            'dias_credito' => 15,
            'notas' => 'Compra de procesadores y memorias',
        ]);

        $responseUpload->assertOk();
        $responseUpload->assertJsonStructure(['message', 'cuenta_id', 'uuid_sat', 'total']);
        $this->assertSame($uuidSimulado, $responseUpload->json('uuid_sat'));
        $this->assertEquals(1160.00, $responseUpload->json('total'));

        // 2. Verificar que se dio de alta al proveedor automáticamente
        $proveedor = Proveedor::query()->where('rfc', 'PRO901231ABC')->first();
        $this->assertNotNull($proveedor);

        // 3. Verificar estado inicial de la Cuenta por Pagar
        /** @var CuentaPorPagar $cxp */
        $cxp = CuentaPorPagar::query()->where('uuid_sat', $uuidSimulado)->first();
        $this->assertNotNull($cxp);
        $this->assertSame('pendiente', $cxp->estado);
        $this->assertEquals(1160.00, $cxp->saldo_pendiente);
        $this->assertEquals(0.00, $cxp->monto_pagado);

        // 4. Aplicar abono parcial de $600
        $responsePagoParcial = $this->actingAs($this->administrador)->postJson(
            route('compras.cxp.pagar', $cxp),
            [
                'monto' => 600.00,
                'fecha_pago' => now()->toDateString(),
                'forma_pago_sat' => '03',
                'referencia_bancaria' => 'SPEI-883921',
            ]
        );

        $responsePagoParcial->assertOk();
        $cxp->refresh();
        $this->assertSame('parcial', $cxp->estado);
        $this->assertEquals(600.00, $cxp->monto_pagado);
        $this->assertEquals(560.00, $cxp->saldo_pendiente);

        // 5. Liquidar el saldo restante de $560
        $responsePagoFinal = $this->actingAs($this->administrador)->postJson(
            route('compras.cxp.pagar', $cxp),
            [
                'monto' => 560.00,
                'fecha_pago' => now()->toDateString(),
                'forma_pago_sat' => '03',
                'referencia_bancaria' => 'SPEI-883944',
            ]
        );

        $responsePagoFinal->assertOk();
        $cxp->refresh();
        $this->assertSame('pagada', $cxp->estado);
        $this->assertEquals(1160.00, $cxp->monto_pagado);
        $this->assertEquals(0.00, $cxp->saldo_pendiente);
        $this->assertCount(2, $cxp->pagos);
    }
}
