<?php

namespace Tests\Feature\Restaurante;

use App\Models\Empresa;
use App\Models\RestauranteArea;
use App\Models\RestauranteMesa;
use App\Models\RestauranteReservacion;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReservacionMesaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Sucursal $sucursal;

    private User $hostess;

    private RestauranteMesa $mesa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->empresa = Empresa::query()->create([
            'nombre' => 'Restaurante Booking Test',
            'giro_comercial' => 'restaurante_cafeteria',
            'plan_suscripcion' => 'avanzado',
            'activo' => true,
        ]);

        $this->sucursal = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-BOOK',
            'nombre' => 'Sucursal Reservas',
            'activo' => true,
        ]);

        $this->hostess = User::factory()->create();
        $this->hostess->assignRole('empleado');
        $this->sucursal->usuarios()->attach($this->hostess->id, [
            'es_gerente' => false,
            'es_principal' => true,
            'activo' => true,
        ]);

        $area = RestauranteArea::query()->create([
            'empresa_id' => $this->empresa->id,
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Terraza Principal',
            'orden' => 1,
            'activo' => true,
        ]);

        $this->mesa = RestauranteMesa::query()->create([
            'restaurante_area_id' => $area->id,
            'numero_o_nombre' => 'Mesa T1',
            'capacidad' => 4,
            'forma' => 'cuadrada',
            'posicion_x' => 15.00,
            'posicion_y' => 20.00,
            'estado' => 'disponible',
        ]);
    }

    #[Test]
    public function hostess_puede_crear_reservacion_y_sentar_comensales_ocupando_la_mesa(): void
    {
        // 1. Agendar reservación para dentro de 1 hora
        $resStore = $this->actingAs($this->hostess)->postJson(route('restaurante.reservaciones.store'), [
            'sucursal_id' => $this->sucursal->id,
            'restaurante_mesa_id' => $this->mesa->id,
            'cliente_nombre' => 'Alejandro Morales',
            'cliente_telefono' => '6621234567',
            'numero_personas' => 3,
            'fecha_hora_reservacion' => now()->addHour()->format('Y-m-d H:i:s'),
            'notas_especiales' => 'Cena de cumpleaños',
        ]);

        $resStore->assertOk();
        $resStore->assertJsonStructure(['folio', 'reservacion']);

        $reservacion = RestauranteReservacion::query()->first();
        $this->assertNotNull($reservacion);
        $this->assertSame('confirmada', $reservacion->estado);
        $this->assertSame('Alejandro Morales', $reservacion->cliente_nombre);

        // 2. Sentar a los comensales al llegar al restaurante
        $resSentar = $this->actingAs($this->hostess)->patchJson(
            route('restaurante.reservaciones.sentar', $reservacion),
            ['restaurante_mesa_id' => $this->mesa->id]
        );

        $resSentar->assertOk();

        // 3. Verificar que la reservación pasó a "sentada" y la mesa física a "ocupada"
        $this->assertSame('sentada', $reservacion->fresh()->estado);
        $this->assertSame('ocupada', $this->mesa->fresh()->estado);
    }
}
