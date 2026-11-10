<?php

namespace Tests\Feature\Finanzas;

use App\Models\CuentaBancaria;
use App\Models\Empresa;
use App\Models\MovimientoBancario;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Finanzas\TesoreriaBancariaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TesoreriaBancariaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Sucursal $sucursal;

    private User $tesorero;

    private TesoreriaBancariaService $service;

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

        $this->tesorero = User::factory()->create();
        $this->service = app(TesoreriaBancariaService::class);
    }

    #[Test]
    public function registra_ingreso_bancario_y_actualiza_saldo_atomo(): void
    {
        /** @var CuentaBancaria $cuenta */
        $cuenta = CuentaBancaria::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'nombre_cuenta' => 'BBVA Operativa',
            'banco' => 'BBVA',
            'numero_cuenta' => '0123456789',
            'clabe' => '012180001234567890',
            'saldo_inicial' => 10000.00,
            'saldo_actual' => 10000.00,
            'activa' => true,
        ]);

        $movimiento = $this->service->registrarIngreso(
            cuenta: $cuenta,
            monto: 3500.50,
            concepto: 'Cobro de reparación e inventario',
            usuario: $this->tesorero,
            referencia: 'SPEI-889922'
        );

        $this->assertInstanceOf(MovimientoBancario::class, $movimiento);
        $this->assertSame('ingreso', $movimiento->tipo);
        $this->assertEquals(10000.00, $movimiento->saldo_anterior);
        $this->assertEquals(13500.50, $movimiento->saldo_posterior);

        $cuenta->refresh();
        $this->assertEquals(13500.50, $cuenta->saldo_actual);
    }

    #[Test]
    public function registra_egreso_bancario_descontando_saldo_y_rechaza_fondos_insuficientes(): void
    {
        /** @var CuentaBancaria $cuenta */
        $cuenta = CuentaBancaria::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'nombre_cuenta' => 'Santander Nómina',
            'banco' => 'Santander',
            'saldo_inicial' => 5000.00,
            'saldo_actual' => 5000.00,
            'activa' => true,
        ]);

        $movimiento = $this->service->registrarEgreso(
            cuenta: $cuenta,
            monto: 2000.00,
            concepto: 'Pago a proveedor de insumos',
            usuario: $this->tesorero
        );

        $this->assertSame('egreso', $movimiento->tipo);
        $cuenta->refresh();
        $this->assertEquals(3000.00, $cuenta->saldo_actual);

        // Intento de egreso superior al saldo disponible
        $this->expectException(InvalidArgumentException::class);
        $this->service->registrarEgreso(
            cuenta: $cuenta,
            monto: 4000.00,
            concepto: 'Pago rechazado por sobregiro',
            usuario: $this->tesorero,
            permitirSobregiro: false
        );
    }

    #[Test]
    public function transfiere_entre_dos_cuentas_de_la_misma_empresa_correctamente(): void
    {
        /** @var CuentaBancaria $cuentaOrigen */
        $cuentaOrigen = CuentaBancaria::query()->create([
            'empresa_id' => $this->empresa->id,
            'nombre_cuenta' => 'Caja General Efectivo',
            'banco' => 'Efectivo',
            'saldo_inicial' => 8000.00,
            'saldo_actual' => 8000.00,
            'activa' => true,
        ]);

        /** @var CuentaBancaria $cuentaDestino */
        $cuentaDestino = CuentaBancaria::query()->create([
            'empresa_id' => $this->empresa->id,
            'nombre_cuenta' => 'Banorte Inversión',
            'banco' => 'Banorte',
            'saldo_inicial' => 2000.00,
            'saldo_actual' => 2000.00,
            'activa' => true,
        ]);

        $resultado = $this->service->transferirEntreCuentas(
            origen: $cuentaOrigen,
            destino: $cuentaDestino,
            monto: 3000.00,
            usuario: $this->tesorero,
            referencia: 'TRF-INT-001'
        );

        $this->assertArrayHasKey('egreso', $resultado);
        $this->assertArrayHasKey('ingreso', $resultado);

        $cuentaOrigen->refresh();
        $cuentaDestino->refresh();

        $this->assertEquals(5000.00, $cuentaOrigen->saldo_actual);
        $this->assertEquals(5000.00, $cuentaDestino->saldo_actual);
    }

    #[Test]
    public function concilia_movimiento_bancario_registrando_timestamp(): void
    {
        /** @var CuentaBancaria $cuenta */
        $cuenta = CuentaBancaria::query()->create([
            'empresa_id' => $this->empresa->id,
            'nombre_cuenta' => 'BBVA Cheques',
            'banco' => 'BBVA',
            'saldo_inicial' => 1000.00,
            'saldo_actual' => 1000.00,
            'activa' => true,
        ]);

        $movimiento = $this->service->registrarIngreso(
            cuenta: $cuenta,
            monto: 500.00,
            concepto: 'Depósito de prueba',
            usuario: $this->tesorero
        );

        $this->assertFalse($movimiento->conciliado);
        $this->assertNull($movimiento->conciliado_at);

        $this->service->conciliarMovimiento($movimiento);

        $movimiento->refresh();
        $this->assertTrue($movimiento->conciliado);
        $this->assertNotNull($movimiento->conciliado_at);
    }
}
