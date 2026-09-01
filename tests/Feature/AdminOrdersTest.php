<?php

use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

test('guests are redirected to the login page when they try to access client repair routes', function () {
    $this->get('/ordenes')->assertRedirect('/login');
    $this->get('/ordenes/create')->assertRedirect('/login');
    $this->get('/ordenes/1')->assertRedirect('/login');
    $this->get('/ordenes/1/pdf')->assertRedirect('/login');
});

test('users can only list orders that belong to them', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $userEquipo = Equipo::create([
        'user_id' => $user->id,
        'tipo' => 'Laptop',
        'marca' => 'HP',
        'modelo' => 'Pavilion',
    ]);

    $otherEquipo = Equipo::create([
        'user_id' => $other->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Inspiron',
    ]);

    OrdenServicio::create([
        'folio' => 'REP-MY-ORDER-1',
        'user_id' => $user->id,
        'equipo_id' => $userEquipo->id,
        'problema_reportado' => 'Se congela.',
        'estado' => 'Recibido',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    OrdenServicio::create([
        'folio' => 'REP-MY-ORDER-2',
        'user_id' => $user->id,
        'equipo_id' => $userEquipo->id,
        'problema_reportado' => 'No carga el sistema.',
        'estado' => 'En reparación',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    OrdenServicio::create([
        'folio' => 'REP-OTHER-ORDER',
        'user_id' => $other->id,
        'equipo_id' => $otherEquipo->id,
        'problema_reportado' => 'No conecta USB.',
        'estado' => 'Recibido',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $response = $this->actingAs($user)->get('/ordenes');

    $response->assertOk()
        ->assertSee('REP-MY-ORDER-1')
        ->assertSee('REP-MY-ORDER-2')
        ->assertDontSee('REP-OTHER-ORDER');
});

test('users cannot create repair orders for equipment that belongs to another user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $equipo = Equipo::create([
        'user_id' => $owner->id,
        'tipo' => 'Desktop',
        'marca' => 'Lenovo',
        'modelo' => 'ThinkCentre M920',
    ]);

    $servicio = Servicio::create([
        'nombre' => 'Revisión de red',
        'descripcion' => 'Diagnóstico de conectividad.',
        'precio' => 425.00,
        'activo' => true,
    ]);

    $response = $this->actingAs($intruder)->post('/ordenes', [
        'equipo_id' => $equipo->id,
        'servicio_id' => $servicio->id,
        'problema_reportado' => 'El equipo se cae de la red y tarda en responder.',
    ]);

    $response->assertNotFound();
});

test('guests cannot access equipment management routes', function () {
    $this->get('/equipos')->assertRedirect('/login');
    $this->get('/equipos/create')->assertRedirect('/login');
    $this->get('/equipos/1/edit')->assertRedirect('/login');
});

test('users can only manage their own equipment', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $equipo = Equipo::create([
        'user_id' => $owner->id,
        'tipo' => 'Laptop',
        'marca' => 'Samsung',
        'modelo' => 'Book4',
    ]);

    $response = $this->actingAs($intruder)->get('/equipos/'.$equipo->id.'/edit');
    $response->assertForbidden();

    $updateResponse = $this->actingAs($intruder)->put('/equipos/'.$equipo->id, [
        'tipo' => 'Laptop',
        'marca' => 'Samsung',
        'modelo' => 'Book X',
        'numero_serie' => 'ABC123',
        'descripcion' => 'Cambio no permitido.',
    ]);

    $updateResponse->assertForbidden();

    $this->assertDatabaseMissing('equipos', [
        'id' => $equipo->id,
        'modelo' => 'Book X',
    ]);
});

test('users can list only their own equipment', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Equipo::create([
        'user_id' => $user->id,
        'tipo' => 'Laptop',
        'marca' => 'Lenovo',
        'modelo' => 'ThinkPad T14',
    ]);

    Equipo::create([
        'user_id' => $user->id,
        'tipo' => 'Desktop',
        'marca' => 'HP',
        'modelo' => 'ProDesk',
    ]);

    Equipo::create([
        'user_id' => $other->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude 5400',
    ]);

    $response = $this->actingAs($user)->get('/equipos');

    $response->assertOk()
        ->assertSee('ThinkPad T14')
        ->assertSee('ProDesk')
        ->assertDontSee('Latitude 5400');
});

test('guests cannot access notification routes', function () {
    $this->get('/notificaciones')->assertRedirect('/login');
    $this->post('/notificaciones/123/leer')->assertRedirect('/login');
    $this->post('/notificaciones/leer-todas')->assertRedirect('/login');
});

test('users can only read their own notifications', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $ownerNotificationId = (string) Str::uuid();
    $intruderNotificationId = (string) Str::uuid();

    DB::table('notifications')->insert([
        [
            'id' => $ownerNotificationId,
            'type' => 'App\\Notifications\\EstadoReparacionActualizado',
            'notifiable_type' => User::class,
            'notifiable_id' => $owner->id,
            'data' => json_encode(['orden_id' => 1, 'mensaje' => 'Se actualizó su reparación.']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => $intruderNotificationId,
            'type' => 'App\\Notifications\\EstadoReparacionActualizado',
            'notifiable_type' => User::class,
            'notifiable_id' => $intruder->id,
            'data' => json_encode(['orden_id' => 2, 'mensaje' => 'No debe leer esto.']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $response = $this->actingAs($intruder)->post('/notificaciones/'.$ownerNotificationId.'/leer');
    $response->assertNotFound();

    $this->assertNull($owner->notifications()->find($ownerNotificationId)->read_at);
    $this->assertNull($intruder->notifications()->find($intruderNotificationId)->read_at);
});

test('users can mark all their own notifications as read', function () {
    $user = User::factory()->create();

    DB::table('notifications')->insert([
        [
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\EstadoReparacionActualizado',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['orden_id' => 10, 'mensaje' => 'Texto 1.']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\EstadoReparacionActualizado',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['orden_id' => 11, 'mensaje' => 'Texto 2.']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $response = $this->actingAs($user)->post('/notificaciones/leer-todas');

    $response->assertRedirect('/notificaciones')
        ->assertSessionHas('success', 'Todas las notificaciones fueron marcadas como leídas.');

    $this->assertEquals(0, $user->unreadNotifications()->count());
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
