<?php

namespace Tests\Feature\Finanzas;

use App\Models\CuentaBancaria;
use App\Models\Empresa;
use App\Models\MovimientoBancario;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BancoControllerTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Sucursal $sucursal;

    private User $admin;

    private CuentaBancaria $cuenta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->empresa = Empresa::query()->create([
            'nombre' => 'Cano Finanzas y Computación',
            'giro_comercial' => 'computadoras_tecnologia',
            'activo' => true,
        ]);

        $this->sucursal = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-01',
            'nombre' => 'Matriz Finanzas',
            'activo' => true,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');

        $this->cuenta = CuentaBancaria::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'nombre_cuenta' => 'BBVA Principal',
            'banco' => 'BBVA',
            'saldo_inicial' => 15000.00,
            'saldo_actual' => 15000.00,
            'activa' => true,
        ]);
    }

    #[Test]
    public function usuario_autenticado_puede_ver_panel_de_bancos(): void
    {
        $response = $this->actingAs($this->admin)->get(route('operacion.bancos.index', $this->empresa->id));

        $response->assertOk();
        $response->assertSee('BBVA Principal');
        $response->assertSee('15,000.00');
    }

    #[Test]
    public function registra_ingreso_bancario_via_formulario_web(): void
    {
        $response = $this->actingAs($this->admin)->post(route('operacion.bancos.movimientos.store', $this->empresa->id), [
            'tipo' => 'ingreso',
            'cuenta_bancaria_id' => $this->cuenta->id,
            'monto' => 2500.00,
            'fecha_operacion' => now()->toDateString(),
            'concepto' => 'Depósito de ventas de mostrador',
            'referencia' => 'DEP-001',
        ]);

        $response->assertRedirect();
        $this->cuenta->refresh();
        $this->assertEquals(17500.00, $this->cuenta->saldo_actual);
        $this->assertDatabaseHas('movimientos_bancarios', [
            'empresa_id' => $this->empresa->id,
            'cuenta_bancaria_id' => $this->cuenta->id,
            'tipo' => 'ingreso',
            'monto' => 2500.00,
            'referencia' => 'DEP-001',
        ]);
    }

    #[Test]
    public function concilia_movimiento_via_endpoint_json(): void
    {
        /** @var MovimientoBancario $movimiento */
        $movimiento = MovimientoBancario::query()->create([
            'empresa_id' => $this->empresa->id,
            'cuenta_bancaria_id' => $this->cuenta->id,
            'user_id' => $this->admin->id,
            'tipo' => 'ingreso',
            'monto' => 1000.00,
            'saldo_anterior' => 15000.00,
            'saldo_posterior' => 16000.00,
            'fecha_operacion' => now()->toDateString(),
            'concepto' => 'Cobro SPEI',
            'conciliado' => false,
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('operacion.bancos.movimientos.conciliar', [
            'empresa' => $this->empresa->id,
            'movimiento' => $movimiento->id,
        ]));

        $response->assertOk();
        $response->assertJsonFragment(['conciliado' => true]);

        $movimiento->refresh();
        $this->assertTrue($movimiento->conciliado);
    }
}
