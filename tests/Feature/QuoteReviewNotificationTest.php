<?php

use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Notifications\CotizacionAprobadaInternamente;
use App\Notifications\CotizacionListaParaAutorizar;
use App\Notifications\CotizacionPendienteRevision;
use App\Notifications\CotizacionRechazadaInternamente;
use App\Notifications\PresupuestoAutorizadoPorCliente;
use App\Notifications\PresupuestoRechazadoPorCliente;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

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

test('client quote notification contains expected database data', function () {
    $cliente = User::factory()->create();

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Client Notification',
        'numero_serie' => 'SERIE-CLIENTE-NOTIFICACION-001',
        'descripcion' => 'Equipo para probar notificación al cliente.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-CLIENTE-NOTIFICACION-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia.',
        'diagnostico' => 'Se detectó una falla en la fuente.',
        'costo_estimado' => 850,
        'estado' => EstadoOrden::ESPERANDO_AUTORIZACION->value,
        'autorizacion' => 'pendiente',
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $notificacion = new CotizacionListaParaAutorizar(
        $orden
    );

    expect($notificacion->via($cliente))
        ->toBe([
            'mail',
            'database',
        ])
        ->and($notificacion->toDatabase($cliente))
        ->toMatchArray([
            'tipo' => 'cotizacion_lista_para_autorizar',
            'orden_id' => $orden->id,
            'folio' => 'REP-CLIENTE-NOTIFICACION-001',
            'estado' => EstadoOrden::ESPERANDO_AUTORIZACION->value,
            'estado_revision' => 'aprobada',
            'autorizacion' => 'pendiente',
            'mensaje' => 'El presupuesto de la reparación '
                .'REP-CLIENTE-NOTIFICACION-001 '
                .'está listo para tu decisión.',
            'url' => route(
                'ordenes.show',
                [
                    'orden' => $orden->id,
                ],
                false
            ),
        ]);
});

test('client quote notification creates a mail message', function () {
    $cliente = User::factory()->create([
        'name' => 'Cliente de prueba',
    ]);

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Client Mail',
        'numero_serie' => 'SERIE-CLIENTE-CORREO-001',
        'descripcion' => 'Equipo para probar correo al cliente.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-CLIENTE-CORREO-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia.',
        'diagnostico' => 'Se detectó una falla en la fuente.',
        'costo_estimado' => 850,
        'estado' => EstadoOrden::ESPERANDO_AUTORIZACION->value,
        'autorizacion' => 'pendiente',
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $mensaje = (new CotizacionListaParaAutorizar(
        $orden
    ))->toMail($cliente);

    expect($mensaje)
        ->toBeInstanceOf(MailMessage::class)
        ->and($mensaje->subject)
        ->toBe(
            'Presupuesto disponible REP-CLIENTE-CORREO-001'
        )
        ->and($mensaje->actionText)
        ->toBe('Revisar presupuesto')
        ->and($mensaje->actionUrl)
        ->toBe(
            route('ordenes.show', [
                'orden' => $orden->id,
            ])
        );
});

test('client quote mail includes diagnosis and estimated cost', function () {
    $cliente = User::factory()->create();

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Client Content',
        'numero_serie' => 'SERIE-CLIENTE-CONTENIDO-001',
        'descripcion' => 'Equipo para probar contenido del correo.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-CLIENTE-CONTENIDO-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia.',
        'diagnostico' => 'Se detectó una falla en la tarjeta principal.',
        'costo_estimado' => 1250.50,
        'estado' => EstadoOrden::ESPERANDO_AUTORIZACION->value,
        'autorizacion' => 'pendiente',
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $mensaje = (new CotizacionListaParaAutorizar(
        $orden
    ))->toMail($cliente);

    expect($mensaje->introLines)
        ->toContain(
            'Diagnóstico: Se detectó una falla en la tarjeta principal.'
        )
        ->toContain(
            'Costo estimado: $1,250.50'
        );
});

test('authorized budget notification points employee to assigned order', function () {
    $cliente = User::factory()->create();
    $empleado = User::factory()->create();

    $empleado->assignRole('empleado');

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Authorized Budget',
        'numero_serie' => 'SERIE-AUTHORIZED-BUDGET-001',
        'descripcion' => 'Equipo para probar autorización.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-AUTHORIZED-BUDGET-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia.',
        'diagnostico' => 'Se detectó una falla en la fuente.',
        'costo_estimado' => 850,
        'estado' => EstadoOrden::ESPERANDO_REFACCION->value,
        'autorizacion' => EstadoAutorizacion::AUTORIZADA->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
        'fecha_autorizacion' => now(),
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $notificacion = new PresupuestoAutorizadoPorCliente(
        $orden
    );

    expect($notificacion->toDatabase($empleado))
        ->toMatchArray([
            'tipo' => 'presupuesto_autorizado_por_cliente',
            'orden_id' => $orden->id,
            'folio' => 'REP-AUTHORIZED-BUDGET-001',
            'estado' => EstadoOrden::ESPERANDO_REFACCION->value,
            'autorizacion' => EstadoAutorizacion::AUTORIZADA->value,
            'mensaje' => 'El cliente autorizó el presupuesto de la reparación '
                .'REP-AUTHORIZED-BUDGET-001.',
            'url' => route(
                'empleado.ordenes.show',
                [
                    'orden' => $orden->id,
                ],
                false
            ),
        ]);
});

test('authorized budget notification points supervisor to dashboard', function () {
    $cliente = User::factory()->create();
    $supervisor = User::factory()->create();

    $supervisor->assignRole('supervisor');

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Supervisor Budget',
        'numero_serie' => 'SERIE-SUPERVISOR-BUDGET-001',
        'descripcion' => 'Equipo para probar aviso al supervisor.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-SUPERVISOR-BUDGET-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia.',
        'diagnostico' => 'Se detectó una falla en la fuente.',
        'costo_estimado' => 850,
        'estado' => EstadoOrden::ESPERANDO_REFACCION->value,
        'autorizacion' => EstadoAutorizacion::AUTORIZADA->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
        'fecha_autorizacion' => now(),
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $notificacion = new PresupuestoAutorizadoPorCliente(
        $orden
    );

    expect($notificacion->toDatabase($supervisor))
        ->toMatchArray([
            'tipo' => 'presupuesto_autorizado_por_cliente',
            'orden_id' => $orden->id,
            'folio' => 'REP-SUPERVISOR-BUDGET-001',
            'autorizacion' => EstadoAutorizacion::AUTORIZADA->value,
            'url' => route(
                'supervisor.dashboard',
                [],
                false
            ),
        ]);
});

test('rejected budget notification points employee to assigned order', function () {
    $cliente = User::factory()->create();
    $empleado = User::factory()->create();

    $empleado->assignRole('empleado');

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Rejected Budget',
        'numero_serie' => 'SERIE-REJECTED-BUDGET-001',
        'descripcion' => 'Equipo para probar rechazo del cliente.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-REJECTED-BUDGET-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia.',
        'diagnostico' => 'Se detectó una falla en la fuente.',
        'costo_estimado' => 850,
        'estado' => EstadoOrden::CANCELADO->value,
        'autorizacion' => EstadoAutorizacion::RECHAZADA->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
        'fecha_autorizacion' => now(),
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $notificacion = new PresupuestoRechazadoPorCliente(
        $orden
    );

    expect($notificacion->toDatabase($empleado))
        ->toMatchArray([
            'tipo' => 'presupuesto_rechazado_por_cliente',
            'orden_id' => $orden->id,
            'folio' => 'REP-REJECTED-BUDGET-001',
            'estado' => EstadoOrden::CANCELADO->value,
            'autorizacion' => EstadoAutorizacion::RECHAZADA->value,
            'mensaje' => 'El cliente rechazó el presupuesto de la reparación '
                .'REP-REJECTED-BUDGET-001.',
            'url' => route(
                'empleado.ordenes.show',
                [
                    'orden' => $orden->id,
                ],
                false
            ),
        ]);
});

test('rejected budget notification points supervisor to dashboard', function () {
    $cliente = User::factory()->create();
    $supervisor = User::factory()->create();

    $supervisor->assignRole('supervisor');

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Supervisor Rejection',
        'numero_serie' => 'SERIE-SUPERVISOR-REJECTION-001',
        'descripcion' => 'Equipo para probar aviso de rechazo.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-SUPERVISOR-REJECTION-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia.',
        'diagnostico' => 'Se detectó una falla en la fuente.',
        'costo_estimado' => 850,
        'estado' => EstadoOrden::CANCELADO->value,
        'autorizacion' => EstadoAutorizacion::RECHAZADA->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
        'fecha_autorizacion' => now(),
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $notificacion = new PresupuestoRechazadoPorCliente(
        $orden
    );

    expect($notificacion->toDatabase($supervisor))
        ->toMatchArray([
            'tipo' => 'presupuesto_rechazado_por_cliente',
            'orden_id' => $orden->id,
            'folio' => 'REP-SUPERVISOR-REJECTION-001',
            'estado' => EstadoOrden::CANCELADO->value,
            'autorizacion' => EstadoAutorizacion::RECHAZADA->value,
            'url' => route(
                'supervisor.dashboard',
                [],
                false
            ),
        ]);
});

test('rejected budget notification creates expected mail message', function () {
    $cliente = User::factory()->create();

    $empleado = User::factory()->create([
        'name' => 'Empleado de prueba',
    ]);

    $empleado->assignRole('empleado');

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Rejection Mail',
        'numero_serie' => 'SERIE-REJECTION-MAIL-001',
        'descripcion' => 'Equipo para probar correo de rechazo.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-REJECTION-MAIL-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia.',
        'diagnostico' => 'Se detectó una falla en la fuente.',
        'costo_estimado' => 850,
        'estado' => EstadoOrden::CANCELADO->value,
        'autorizacion' => EstadoAutorizacion::RECHAZADA->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
        'fecha_autorizacion' => now(),
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $mensaje = (new PresupuestoRechazadoPorCliente(
        $orden
    ))->toMail($empleado);

    expect($mensaje)
        ->toBeInstanceOf(MailMessage::class)
        ->and($mensaje->subject)
        ->toBe(
            'Presupuesto rechazado REP-REJECTION-MAIL-001'
        )
        ->and($mensaje->actionText)
        ->toBe('Consultar reparación')
        ->and($mensaje->actionUrl)
        ->toBe(
            route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ])
        )
        ->and($mensaje->introLines)
        ->toContain(
            'El cliente rechazó el presupuesto de la reparación.'
        )
        ->toContain(
            'La orden quedó en estado: '
            .EstadoOrden::CANCELADO->value
        );
});
