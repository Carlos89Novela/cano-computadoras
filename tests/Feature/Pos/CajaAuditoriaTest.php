<?php

namespace Tests\Feature\Pos;

use App\Models\CajaTurno;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CajaAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Sucursal $sucursal;

    private User $supervisor;

    private User $cajero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->empresa = Empresa::query()->create([
            'nombre' => 'Cano Computadoras Test',
            'activo' => true,
        ]);

        $this->sucursal = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-01',
            'nombre' => 'Sucursal Centro',
            'activo' => true,
        ]);

        // Crear usuarios sin empresa_id directo en la tabla users
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('supervisor');

        $this->cajero = User::factory()->create();
        $this->cajero->assignRole('empleado');
    }

    #[Test]
    public function supervisor_puede_ver_listado_de_turnos(): void
    {
        CajaTurno::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'user_id' => $this->cajero->id,
            'apertura_at' => now(),
            'monto_inicial' => 1000.00,
            'monto_esperado_efectivo' => 1000.00,
            'estado' => 'abierto',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->get(route('pos.cajas.index'));

        $response->assertOk();
        $response->assertViewIs('pos.cajas.index');
        $response->assertSee('Turno #');
        $response->assertSee('Sucursal Centro');
    }

    #[Test]
    public function supervisor_puede_registrar_retiro_de_efectivo_en_turno_abierto(): void
    {
        /** @var CajaTurno $turno */
        $turno = CajaTurno::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'user_id' => $this->cajero->id,
            'apertura_at' => now(),
            'monto_inicial' => 1000.00,
            'total_ventas_efectivo' => 500.00,
            'monto_esperado_efectivo' => 1500.00,
            'estado' => 'abierto',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->post(route('pos.cajas.movimiento', $turno), [
                'tipo' => 'retiro',
                'concepto' => 'Recolección de valores parcial',
                'monto' => 400.00,
                'motivo' => 'Exceso de efectivo en caja de mostrador',
                'comprobante_numero' => 'REC-001',
            ]);

        $response->assertSessionHas('status');

        $this->assertDatabaseHas('caja_movimientos', [
            'caja_turno_id' => $turno->id,
            'tipo' => 'retiro',
            'monto' => '400.00',
            'comprobante_numero' => 'REC-001',
        ]);

        $turno->refresh();
        $this->assertEquals('400.00', (string) $turno->total_retiros);
        $this->assertEquals('1100.00', (string) $turno->monto_esperado_efectivo);
    }

    #[Test]
    public function no_permite_retiro_superior_al_efectivo_disponible(): void
    {
        /** @var CajaTurno $turno */
        $turno = CajaTurno::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'user_id' => $this->cajero->id,
            'apertura_at' => now(),
            'monto_inicial' => 300.00,
            'monto_esperado_efectivo' => 300.00,
            'estado' => 'abierto',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->post(route('pos.cajas.movimiento', $turno), [
                'tipo' => 'retiro',
                'concepto' => 'Retiro excesivo',
                'monto' => 500.00,
                'motivo' => 'Prueba de validación',
            ]);

        $response->assertSessionHasErrors(['monto']);
        $this->assertDatabaseCount('caja_movimientos', 0);
    }

    #[Test]
    public function supervisor_puede_descargar_corte_en_pdf(): void
    {
        /** @var CajaTurno $turno */
        $turno = CajaTurno::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'user_id' => $this->cajero->id,
            'apertura_at' => now()->subHours(8),
            'cierre_at' => now(),
            'monto_inicial' => 1000.00,
            'total_ventas_efectivo' => 800.00,
            'monto_esperado_efectivo' => 1800.00,
            'monto_real_efectivo' => 1800.00,
            'diferencia_efectivo' => 0.00,
            'estado' => 'cerrado',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->get(route('pos.cajas.corte-pdf', $turno));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }
}
