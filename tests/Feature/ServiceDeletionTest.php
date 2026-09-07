<?php

use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function crearAdministradorServicios(): User
{
    Role::firstOrCreate([
        'name' => 'administrador',
        'guard_name' => 'web',
    ]);

    $administrador = User::factory()->create();

    $administrador->assignRole('administrador');

    return $administrador;
}

test('admin can delete a service without repair orders', function () {
    $administrador = crearAdministradorServicios();

    $servicio = Servicio::query()->create([
        'nombre' => 'Servicio temporal',
        'descripcion' => 'Servicio sin órdenes relacionadas.',
        'precio' => 350.00,
        'activo' => true,
    ]);

    $response = $this
        ->actingAs($administrador)
        ->delete(
            route('admin.servicios.destroy', [
                'servicio' => $servicio->id,
            ])
        );

    $response
        ->assertRedirect(route('admin.servicios.index'))
        ->assertSessionHas(
            'success',
            'Servicio eliminado correctamente.'
        );

    $this->assertModelMissing($servicio);
});

test('service with repair orders is disabled instead of deleted', function () {
    $administrador = crearAdministradorServicios();
    $cliente = User::factory()->create();

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude de prueba',
        'numero_serie' => 'TEST-SERVICE-001',
        'descripcion' => 'Equipo para probar servicios relacionados.',
    ]);

    $servicio = Servicio::query()->create([
        'nombre' => 'Diagnóstico especializado',
        'descripcion' => 'Servicio utilizado por una orden.',
        'precio' => 850.00,
        'activo' => true,
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'TEST-SERVICE-ORDER-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => $servicio->id,
        'problema_reportado' => 'El equipo presenta una falla intermitente al iniciar.',
        'estado' => 'Recibido',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $response = $this
        ->actingAs($administrador)
        ->delete(
            route('admin.servicios.destroy', [
                'servicio' => $servicio->id,
            ])
        );

    $response
        ->assertRedirect(route('admin.servicios.index'))
        ->assertSessionHas(
            'success',
            'El servicio tiene reparaciones relacionadas y fue desactivado.'
        );

    $this->assertDatabaseHas('servicios', [
        'id' => $servicio->id,
        'activo' => false,
    ]);

    $this->assertDatabaseHas('orden_servicios', [
        'id' => $orden->id,
        'servicio_id' => $servicio->id,
    ]);

    $this->assertModelExists($orden);
});

test('regular user cannot delete an administrative service', function () {
    $usuario = User::factory()->create();

    $servicio = Servicio::query()->create([
        'nombre' => 'Servicio protegido',
        'descripcion' => 'Servicio protegido por el middleware administrativo.',
        'precio' => 500.00,
        'activo' => true,
    ]);

    $response = $this
        ->actingAs($usuario)
        ->delete(
            route('admin.servicios.destroy', [
                'servicio' => $servicio->id,
            ])
        );

    $response->assertForbidden();

    $this->assertDatabaseHas('servicios', [
        'id' => $servicio->id,
        'activo' => true,
    ]);
});

test('disabled service is not displayed when creating a repair order', function () {
    $usuario = User::factory()->create();

    Equipo::query()->create([
        'user_id' => $usuario->id,
        'tipo' => 'Laptop',
        'marca' => 'HP',
        'modelo' => 'ProBook de prueba',
        'numero_serie' => 'TEST-SERVICE-VISIBILITY-001',
        'descripcion' => 'Equipo para probar la visibilidad de servicios.',
    ]);

    $servicioActivo = Servicio::query()->create([
        'nombre' => 'Servicio disponible',
        'descripcion' => 'Servicio visible para nuevas órdenes.',
        'precio' => 450.00,
        'activo' => true,
    ]);

    $servicioInactivo = Servicio::query()->create([
        'nombre' => 'Servicio desactivado',
        'descripcion' => 'Servicio que no debe aparecer en nuevas órdenes.',
        'precio' => 650.00,
        'activo' => false,
    ]);

    $response = $this
        ->actingAs($usuario)
        ->get(route('ordenes.create'));

    $response
        ->assertOk()
        ->assertSee($servicioActivo->nombre)
        ->assertDontSee($servicioInactivo->nombre);
});

test('user cannot create a repair order with a disabled service', function () {
    $usuario = User::factory()->create();

    $equipo = Equipo::query()->create([
        'user_id' => $usuario->id,
        'tipo' => 'Laptop',
        'marca' => 'Lenovo',
        'modelo' => 'ThinkPad de prueba',
        'numero_serie' => 'TEST-INACTIVE-SERVICE-001',
        'descripcion' => 'Equipo para validar servicios inactivos.',
    ]);

    $servicio = Servicio::query()->create([
        'nombre' => 'Servicio no disponible',
        'descripcion' => 'Servicio desactivado que no debe aceptarse.',
        'precio' => 700.00,
        'activo' => false,
    ]);

    $response = $this
        ->actingAs($usuario)
        ->from(route('ordenes.create'))
        ->post(route('ordenes.store'), [
            'equipo_id' => $equipo->id,
            'servicio_id' => $servicio->id,
            'problema_reportado' => 'El equipo se apaga después de algunos minutos de uso.',
        ]);

    $response
        ->assertRedirect(route('ordenes.create'))
        ->assertSessionHasErrors('servicio_id');

    $this->assertDatabaseMissing('orden_servicios', [
        'user_id' => $usuario->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => $servicio->id,
    ]);
});
