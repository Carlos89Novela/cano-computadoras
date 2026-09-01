<?php

use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\Servicio;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('admin users can access the orders management page', function () {
    Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('administrador');

    $response = $this->actingAs($user)->get('/admin/ordenes');

    $response->assertOk()
        ->assertSee('Administrar reparaciones');
});

test('regular users cannot access the admin orders page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/admin/ordenes');

    $response->assertForbidden();
});

test('authenticated users can access the dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()
        ->assertSee('Dashboard');
});

test('admin users can create a service', function () {
    Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole('administrador');

    $response = $this->actingAs($admin)->post('/admin/servicios', [
        'nombre' => 'Diagnóstico avanzado',
        'descripcion' => 'Revisión profunda de hardware y software.',
        'precio' => 850.00,
        'activo' => true,
    ]);

    $response->assertRedirect(route('admin.servicios.index'))
        ->assertSessionHas('success', 'Servicio registrado correctamente.');

    $this->assertDatabaseHas('servicios', [
        'nombre' => 'Diagnóstico avanzado',
        'precio' => 850.00,
        'activo' => true,
    ]);
});

test('admin users can update an existing service', function () {
    Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole('administrador');

    $servicio = Servicio::create([
        'nombre' => 'Revisión básica',
        'descripcion' => 'Servicio antiguo.',
        'precio' => 300.00,
        'activo' => true,
    ]);

    $response = $this->actingAs($admin)->put('/admin/servicios/'.$servicio->id, [
        'nombre' => 'Revisión premium',
        'descripcion' => 'Servicio actualizado.',
        'precio' => 450.50,
        'activo' => true,
    ]);

    $response->assertRedirect(route('admin.servicios.index'))
        ->assertSessionHas('success', 'Servicio actualizado correctamente.');

    $this->assertDatabaseHas('servicios', [
        'id' => $servicio->id,
        'nombre' => 'Revisión premium',
        'precio' => 450.50,
    ]);
});

test('admin users can update an order status', function () {
    Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole('administrador');

    $cliente = User::factory()->create();
    $equipo = Equipo::create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Inspiron 15',
    ]);

    $orden = OrdenServicio::create([
        'folio' => 'REP-TEST-'.now()->timestamp,
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'problema_reportado' => 'Pantalla apagada',
        'estado' => 'Recibido',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $response = $this->actingAs($admin)->put('/admin/ordenes/'.$orden->id, [
        'estado' => 'En reparación',
        'diagnostico' => 'Se requiere revisión del panel.',
        'costo_estimado' => 600.00,
        'costo_final' => 680.00,
        'comentario' => 'Se inició la reparación.',
    ]);

    $response->assertRedirect(route('admin.ordenes.edit', ['orden' => $orden->id]));

    $this->assertDatabaseHas('orden_servicios', [
        'id' => $orden->id,
        'estado' => 'En reparación',
        'diagnostico' => 'Se requiere revisión del panel.',
    ]);
});

test('authenticated users can create a repair order for their own equipment', function () {
    $user = User::factory()->create();
    $equipo = Equipo::create([
        'user_id' => $user->id,
        'tipo' => 'Laptop',
        'marca' => 'HP',
        'modelo' => 'EliteBook 840',
    ]);

    $servicio = Servicio::create([
        'nombre' => 'Mantenimiento preventivo',
        'descripcion' => 'Servicio de revisión general.',
        'precio' => 550.00,
        'activo' => true,
    ]);

    $response = $this->actingAs($user)->post('/ordenes', [
        'equipo_id' => $equipo->id,
        'servicio_id' => $servicio->id,
        'problema_reportado' => 'La laptop se apaga sin razón y tarda en encender.',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('orden_servicios', [
        'user_id' => $user->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => $servicio->id,
        'estado' => 'Recibido',
    ]);

    $this->assertDatabaseHas('historial_reparaciones', [
        'estado' => 'Recibido',
        'comentarios' => 'Solicitud de reparación registrada.',
    ]);
});

test('the public tracking page shows the order by folio', function () {
    $user = User::factory()->create();
    $equipo = Equipo::create([
        'user_id' => $user->id,
        'tipo' => 'Desktop',
        'marca' => 'Lenovo',
        'modelo' => 'ThinkCentre',
    ]);

    $orden = OrdenServicio::create([
        'folio' => 'REP-TRACK-1234',
        'user_id' => $user->id,
        'equipo_id' => $equipo->id,
        'problema_reportado' => 'El equipo no prende.',
        'estado' => 'Recibido',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $orden->historial()->create([
        'user_id' => $user->id,
        'estado' => 'Recibido',
        'comentarios' => 'Solicitud de reparación registrada.',
    ]);

    $response = $this->get('/seguimiento/REP-TRACK-1234');

    $response->assertOk()
        ->assertSee('REP-TRACK-1234')
        ->assertSee('El equipo no prende.');
});

test('users cannot view orders that belong to another user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $equipo = Equipo::create([
        'user_id' => $owner->id,
        'tipo' => 'Laptop',
        'marca' => 'Asus',
        'modelo' => 'ZenBook',
    ]);

    $orden = OrdenServicio::create([
        'folio' => 'REP-OTHER-123',
        'user_id' => $owner->id,
        'equipo_id' => $equipo->id,
        'problema_reportado' => 'La batería no dura.',
        'estado' => 'Recibido',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $response = $this->actingAs($intruder)->get('/ordenes/'.$orden->id);

    $response->assertForbidden();
});

test('users cannot authorize another persons repair order', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $equipo = Equipo::create([
        'user_id' => $owner->id,
        'tipo' => 'Laptop',
        'marca' => 'Acer',
        'modelo' => 'Nitro',
    ]);

    $orden = OrdenServicio::create([
        'folio' => 'REP-OTHER-AUTH',
        'user_id' => $owner->id,
        'equipo_id' => $equipo->id,
        'problema_reportado' => 'No reconoce Wi-Fi.',
        'estado' => 'Esperando autorización',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $response = $this->actingAs($intruder)->post('/ordenes/'.$orden->id.'/autorizar', [
        'decision' => 'autorizada',
    ]);

    $response->assertForbidden();
});

test('regular users cannot access the admin panel routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/servicios')->assertForbidden();
    $this->actingAs($user)->get('/admin/ordenes/data')->assertForbidden();
});

test('users cannot download pdfs for orders that belong to another user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $equipo = Equipo::create([
        'user_id' => $owner->id,
        'tipo' => 'Laptop',
        'marca' => 'MSI',
        'modelo' => 'GF63',
    ]);

    $orden = OrdenServicio::create([
        'folio' => 'REP-OTHER-PDF',
        'user_id' => $owner->id,
        'equipo_id' => $equipo->id,
        'problema_reportado' => 'Se recalienta al usar editor.',
        'estado' => 'Recibido',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $response = $this->actingAs($intruder)->get('/ordenes/'.$orden->id.'/pdf');

    $response->assertForbidden();
});

test('logged in users can access their own order detail page', function () {
    $user = User::factory()->create();
    $equipo = Equipo::create([
        'user_id' => $user->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude 5420',
    ]);

    $orden = OrdenServicio::create([
        'folio' => 'REP-OWN-DETAIL',
        'user_id' => $user->id,
        'equipo_id' => $equipo->id,
        'problema_reportado' => 'El equipo tarda en arrancar.',
        'estado' => 'En reparación',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $orden->historial()->create([
        'user_id' => $user->id,
        'estado' => 'En reparación',
        'comentarios' => 'Se inició la revisión técnica.',
    ]);

    $response = $this->actingAs($user)->get('/ordenes/'.$orden->id);

    $response->assertOk()
        ->assertSee('REP-OWN-DETAIL')
        ->assertSee('El equipo tarda en arrancar.');
});
