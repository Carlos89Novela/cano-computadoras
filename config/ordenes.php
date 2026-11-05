<?php

use App\Enums\EstadoOrden;

return [
    'clases_estado' => [
        EstadoOrden::RECIBIDO->value => [
            'badge' => 'estado-badge estado-recibido',
        ],

        EstadoOrden::EN_DIAGNOSTICO->value => [
            'badge' => 'estado-badge estado-en-diagnostico',
        ],

        EstadoOrden::ESPERANDO_AUTORIZACION->value => [
            'badge' => 'estado-badge estado-esperando-autorizacion',
        ],

        EstadoOrden::ESPERANDO_REFACCION->value => [
            'badge' => 'estado-badge estado-esperando-refaccion',
        ],

        EstadoOrden::EN_REPARACION->value => [
            'badge' => 'estado-badge estado-en-reparacion',
        ],

        EstadoOrden::EN_PRUEBAS->value => [
            'badge' => 'estado-badge estado-en-pruebas',
        ],

        EstadoOrden::LISTO_PARA_ENTREGA->value => [
            'badge' => 'estado-badge estado-listo-para-entrega',
        ],

        EstadoOrden::ENTREGADO->value => [
            'badge' => 'estado-badge estado-entregado',
        ],

        EstadoOrden::CANCELADO->value => [
            'badge' => 'estado-badge estado-cancelado',
        ],
    ],

    'clase_estado_desconocido' => [
        'badge' => 'estado-badge estado-desconocido',
    ],
];
