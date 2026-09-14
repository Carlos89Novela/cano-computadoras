<?php

use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Notifications\CotizacionAprobadaInternamente;
use App\Notifications\CotizacionPendienteRevision;
use App\Notifications\CotizacionRechazadaInternamente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;

uses(RefreshDatabase::class);

test('pending quote review notification contains expected database data', function () {
    $cliente = User::factory()->create();
    $supervisor = User::factory()->create();

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Notification',
        'numero_serie' => 'SERIE-NOTIFICACION-001',
        'descripcion' => 'Equipo para probar notificaciones.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-NOTIFICACION-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia.',
        'diagnostico' => 'Se detectó una falla en la fuente.',
        'costo_estimado' => 850,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::PENDIENTE,
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $notificacion = new CotizacionPendienteRevision(
        $orden
    );

    expect($notificacion->via($supervisor))
        ->toBe([
            'mail',
            'database',
        ])
        ->and($notificacion->toDatabase($supervisor))
        ->toMatchArray([
            'tipo' => 'cotizacion_pendiente_revision',
            'orden_id' => $orden->id,
            'folio' => 'REP-NOTIFICACION-001',
            'estado_revision' => 'pendiente',
            'mensaje' => 'La cotización REP-NOTIFICACION-001 está pendiente de revisión.',
            'url' => route('supervisor.cotizaciones.show', [
                'orden' => $orden->id,
            ], false),
        ]);
});

test('pending quote review notification creates a mail message', function () {
    $cliente = User::factory()->create();
    $supervisor = User::factory()->create([
        'name' => 'Supervisor de prueba',
    ]);

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Mail',
        'numero_serie' => 'SERIE-CORREO-001',
        'descripcion' => 'Equipo para probar correo.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-CORREO-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia.',
        'diagnostico' => 'Se detectó una falla en la fuente.',
        'costo_estimado' => 850,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::PENDIENTE,
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $mensaje = (new CotizacionPendienteRevision(
        $orden
    ))->toMail($supervisor);

    expect($mensaje)
        ->toBeInstanceOf(MailMessage::class)
        ->and($mensaje->subject)
        ->toBe('Cotización pendiente REP-CORREO-001')
        ->and($mensaje->actionText)
        ->toBe('Revisar cotización')
        ->and($mensaje->actionUrl)
        ->toBe(
            route('supervisor.cotizaciones.show', [
                'orden' => $orden->id,
            ], false)
        );
});

test('approved quote notification contains expected database data', function () {
    $cliente = User::factory()->create();
    $empleado = User::factory()->create();

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Approved Notification',
        'numero_serie' => 'SERIE-APROBADA-001',
        'descripcion' => 'Equipo para probar aprobación interna.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-APROBADA-NOTIFICACION-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia.',
        'diagnostico' => 'Se detectó una falla en la fuente.',
        'costo_estimado' => 850,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $notificacion = new CotizacionAprobadaInternamente(
        $orden
    );

    expect($notificacion->via($empleado))
        ->toBe([
            'mail',
            'database',
        ])
        ->and($notificacion->toDatabase($empleado))
        ->toMatchArray([
            'tipo' => 'cotizacion_aprobada_internamente',
            'orden_id' => $orden->id,
            'folio' => 'REP-APROBADA-NOTIFICACION-001',
            'estado_revision' => 'aprobada',
            'mensaje' => 'La cotización REP-APROBADA-NOTIFICACION-001 fue aprobada internamente.',
            'url' => route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ], false),
        ]);
});

test('approved quote notification creates a mail message', function () {
    $cliente = User::factory()->create();

    $empleado = User::factory()->create([
        'name' => 'Empleado de prueba',
    ]);

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Approved Mail',
        'numero_serie' => 'SERIE-APROBADA-CORREO-001',
        'descripcion' => 'Equipo para probar correo de aprobación.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-APROBADA-CORREO-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia.',
        'diagnostico' => 'Se detectó una falla en la fuente.',
        'costo_estimado' => 850,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $mensaje = (new CotizacionAprobadaInternamente(
        $orden
    ))->toMail($empleado);

    expect($mensaje)
        ->toBeInstanceOf(MailMessage::class)
        ->and($mensaje->subject)
        ->toBe(
            'Cotización aprobada REP-APROBADA-CORREO-001'
        )
        ->and($mensaje->actionText)
        ->toBe('Consultar reparación')
        ->and($mensaje->actionUrl)
        ->toBe(
            route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ], false)
        );
});

test('rejected quote notification contains expected database data', function () {
    $cliente = User::factory()->create();
    $empleado = User::factory()->create();

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Rejected Notification',
        'numero_serie' => 'SERIE-RECHAZADA-001',
        'descripcion' => 'Equipo para probar rechazo interno.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-RECHAZADA-NOTIFICACION-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia.',
        'diagnostico' => 'Se detectó una falla en la fuente.',
        'costo_estimado' => 850,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::RECHAZADA,
        'observacion_revision_cotizacion' => 'Corregir el costo de la refacción.',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $observacion = 'Corregir el costo de la refacción.';

    $notificacion = new CotizacionRechazadaInternamente(
        $orden,
        $observacion
    );

    expect($notificacion->via($empleado))
        ->toBe([
            'mail',
            'database',
        ])
        ->and($notificacion->toDatabase($empleado))
        ->toMatchArray([
            'tipo' => 'cotizacion_rechazada_internamente',
            'orden_id' => $orden->id,
            'folio' => 'REP-RECHAZADA-NOTIFICACION-001',
            'estado_revision' => 'rechazada',
            'observacion' => 'Corregir el costo de la refacción.',
            'mensaje' => 'La cotización REP-RECHAZADA-NOTIFICACION-001 fue devuelta para corrección.',
            'url' => route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ], false),
        ]);
});

test('rejected quote notification creates a mail message', function () {
    $cliente = User::factory()->create();

    $empleado = User::factory()->create([
        'name' => 'Empleado de prueba',
    ]);

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Rejected Mail',
        'numero_serie' => 'SERIE-RECHAZADA-CORREO-001',
        'descripcion' => 'Equipo para probar correo de rechazo.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-RECHAZADA-CORREO-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia.',
        'diagnostico' => 'Se detectó una falla en la fuente.',
        'costo_estimado' => 850,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::RECHAZADA,
        'observacion_revision_cotizacion' => 'Corregir el costo de la refacción.',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $mensaje = (new CotizacionRechazadaInternamente(
        $orden,
        'Corregir el costo de la refacción.'
    ))->toMail($empleado);

    expect($mensaje)
        ->toBeInstanceOf(MailMessage::class)
        ->and($mensaje->subject)
        ->toBe(
            'Cotización devuelta REP-RECHAZADA-CORREO-001'
        )
        ->and($mensaje->actionText)
        ->toBe('Corregir cotización')
        ->and($mensaje->actionUrl)
        ->toBe(
            route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ], false)
        );
});

test('rejected quote mail includes the review observation', function () {
    $cliente = User::factory()->create();
    $empleado = User::factory()->create();

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Rejected Reason',
        'numero_serie' => 'SERIE-RECHAZADA-MOTIVO-001',
        'descripcion' => 'Equipo para probar motivo de rechazo.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-RECHAZADA-MOTIVO-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia.',
        'diagnostico' => 'Se detectó una falla en la fuente.',
        'costo_estimado' => 850,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::RECHAZADA,
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $mensaje = (new CotizacionRechazadaInternamente(
        $orden,
        'Verificar el precio de la pieza.'
    ))->toMail($empleado);

    expect($mensaje->introLines)
        ->toContain(
            'Motivo: Verificar el precio de la pieza.'
        );
});
