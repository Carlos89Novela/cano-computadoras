<?php

namespace Tests\Feature\Restaurante;

use App\Models\CategoriaProducto;
use App\Models\Comanda;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\ProductoReceta;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComandaKdsTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Sucursal $sucursal;

    private User $mesero;

    private Producto $platilloCocina;

    private Producto $bebidaBarra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->empresa = Empresa::query()->create([
            'nombre' => 'Restaurante KDS Test',
            'giro_comercial' => 'restaurante_cafeteria',
            'plan_suscripcion' => 'avanzado',
            'activo' => true,
        ]);

        $this->sucursal = Sucursal::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SUC-KDS',
            'nombre' => 'Sucursal Restaurante',
            'activo' => true,
        ]);

        $categoria = CategoriaProducto::query()->create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Alimentos y Bebidas',
            'activo' => true,
        ]);

        $this->mesero = User::factory()->create();
        $this->mesero->assignRole('empleado');
        $this->sucursal->usuarios()->attach($this->mesero->id, [
            'es_gerente' => false,
            'es_principal' => true,
            'activo' => true,
        ]);

        // Platillo de cocina
        $this->platilloCocina = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $categoria->id,
            'sku' => 'PLAT-TACOS',
            'nombre' => 'Orden de Tacos Ribeye',
            'unidad_medida' => 'orden',
            'costo_referencia' => 60.00,
            'precio_base' => 195.00,
            'es_receta' => true,
            'activo' => true,
        ]);

        ProductoReceta::query()->create([
            'empresa_id' => $this->empresa->id,
            'producto_id' => $this->platilloCocina->id,
            'estacion_cocina' => 'cocina',
            'tiempo_preparacion_minutos' => 12,
            'activo' => true,
        ]);

        // Bebida de barra
        $this->bebidaBarra = Producto::query()->create([
            'empresa_id' => $this->empresa->id,
            'categoria_producto_id' => $categoria->id,
            'sku' => 'BEB-MOJITO',
            'nombre' => 'Mojito Tradicional',
            'unidad_medida' => 'pieza',
            'costo_referencia' => 25.00,
            'precio_base' => 110.00,
            'es_receta' => true,
            'activo' => true,
        ]);

        ProductoReceta::query()->create([
            'empresa_id' => $this->empresa->id,
            'producto_id' => $this->bebidaBarra->id,
            'estacion_cocina' => 'barra',
            'tiempo_preparacion_minutos' => 5,
            'activo' => true,
        ]);
    }

    #[Test]
    public function mesero_puede_enviar_comanda_y_aparece_segmentada_en_kds(): void
    {
        // 1. Envío desde el comandero
        $response = $this->actingAs($this->mesero)->postJson(route('restaurante.comandero.enviar'), [
            'sucursal_id' => $this->sucursal->id,
            'mesa_o_referencia' => 'Mesa 5',
            'items' => [
                [
                    'producto_id' => $this->platilloCocina->id,
                    'cantidad' => 2,
                    'notas_especiales' => 'Sin cebolla',
                ],
                [
                    'producto_id' => $this->bebidaBarra->id,
                    'cantidad' => 1,
                    'notas_especiales' => 'Poco hielo',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['folio', 'comanda_id']);

        $comanda = Comanda::query()->first();
        $this->assertNotNull($comanda);
        $this->assertSame('Mesa 5', $comanda->mesa_o_referencia);
        $this->assertSame('pendiente', $comanda->estado);
        $this->assertCount(2, $comanda->items);

        // 2. Comprobar segmentación en monitor KDS (solo cocina)
        $resKdsCocina = $this->actingAs($this->mesero)->getJson(
            route('restaurante.kds.comandas-activas', [
                'sucursal_id' => $this->sucursal->id,
                'estacion' => 'cocina',
            ])
        );

        $resKdsCocina->assertOk();
        $dataCocina = $resKdsCocina->json();
        $this->assertCount(1, $dataCocina);
        $this->assertSame('Orden de Tacos Ribeye', $dataCocina[0]['items'][0]['producto']['nombre']);

        // 3. Comprobar segmentación en monitor KDS (solo barra)
        $resKdsBarra = $this->actingAs($this->mesero)->getJson(
            route('restaurante.kds.comandas-activas', [
                'sucursal_id' => $this->sucursal->id,
                'estacion' => 'barra',
            ])
        );

        $resKdsBarra->assertOk();
        $dataBarra = $resKdsBarra->json();
        $this->assertCount(1, $dataBarra);
        $this->assertSame('Mojito Tradicional', $dataBarra[0]['items'][0]['producto']['nombre']);

        // 4. Completar comanda
        $resCompletar = $this->actingAs($this->mesero)->postJson(
            route('restaurante.kds.completar', $comanda)
        );

        $resCompletar->assertOk();
        $this->assertSame('completada', $comanda->fresh()->estado);
    }
}
