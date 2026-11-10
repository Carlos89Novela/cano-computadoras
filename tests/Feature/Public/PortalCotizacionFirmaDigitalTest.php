<?php

namespace Tests\Feature\Public;

use App\Models\Empresa;
use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalCotizacionFirmaDigitalTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Sucursal $sucursal;

    private User $cliente;

    private Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->empresa = Empresa::query()->create([
            'nombre' => 'Cano Taller Central',
            'giro_comercial' => 'computadoras_tecnologia',
            'plan_suscripcion' => 'avanzado',
            'activo' => true,
        ]);

        $this->sucursal = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-TAL',
            'nombre' => 'Matriz Taller',
            'activo' => true,
        ]);

        $this->cliente = User::factory()->create();

        $this->equipo = Equipo::query()->create([
            'user_id' => $this->cliente->id,
            'tipo' => 'Laptop',
            'marca' => 'Dell',
            'modelo' => 'Latitude 5420',
            'numero_serie' => 'SN-LAT-2026',
            'activo' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function crearOrdenPrueba(array $atributos = []): OrdenServicio
    {
        /** @var OrdenServicio $orden */
        $orden = OrdenServicio::query()->create(array_merge([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'user_id' => $this->cliente->id,
            'equipo_id' => $this->equipo->id,
            'folio' => 'ORD-TAL-'.Str::upper(Str::random(5)),
            'token_publico' => (string) Str::uuid(),
            'token_seguimiento' => Str::random(32),
            'problema_reportado' => 'Problema reportado en equipo',
            'fecha_ingreso' => now(),
            'estado' => 'esperando_aprobacion',
            'costo_estimado' => 1000.00,
        ], $atributos));

        return $orden;
    }

    #[Test]
    public function cliente_puede_ver_su_cotizacion_publica_por_token_sin_autenticarse(): void
    {
        $orden = $this->crearOrdenPrueba([
            'folio' => 'ORD-TAL-001',
            'costo_estimado' => 1250.00,
        ]);

        $response = $this->get(route('public.cotizacion.show', $orden->token_publico));

        $response->assertOk();
        $response->assertSee('ORD-TAL-001');
        $response->assertSee('1,250.00');
    }

    #[Test]
    public function cliente_autoriza_con_firma_digital_base64_cambiando_estado_y_guardando_png(): void
    {
        $orden = $this->crearOrdenPrueba([
            'folio' => 'ORD-TAL-002',
            'costo_estimado' => 850.00,
        ]);

        // 1px PNG transparente en Base64
        $firmaFalsaBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

        $response = $this->postJson(route('public.cotizacion.autorizar', $orden->token_publico), [
            'firma_base64' => $firmaFalsaBase64,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['estado' => 'autorizada']);

        $orden->refresh();
        $this->assertSame('autorizada', (string) $orden->estado);
        $this->assertNotNull($orden->firmado_at);
        $this->assertNotNull($orden->firma_digital_path);
        Storage::disk('local')->assertExists((string) $orden->firma_digital_path);
    }

    #[Test]
    public function cliente_puede_rechazar_cotizacion_con_motivo(): void
    {
        $orden = $this->crearOrdenPrueba([
            'folio' => 'ORD-TAL-003',
            'costo_estimado' => 4500.00,
        ]);

        $response = $this->postJson(route('public.cotizacion.rechazar', $orden->token_publico), [
            'motivo_rechazo' => 'Costo demasiado elevado para el equipo.',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['estado' => 'rechazada']);

        $orden->refresh();
        $this->assertSame('rechazada', (string) $orden->estado);
        $this->assertSame('Costo demasiado elevado para el equipo.', $orden->motivo_rechazo);
    }
}
