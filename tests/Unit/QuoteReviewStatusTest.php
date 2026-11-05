<?php

use App\Enums\EstadoRevisionCotizacion;

test('quote review status exposes the expected values', function () {
    expect(
        EstadoRevisionCotizacion::valores()
    )->toBe([
        'sin_solicitar',
        'pendiente',
        'aprobada',
        'rechazada',
    ]);
});

test('only pending status identifies a pending review', function () {
    expect(
        EstadoRevisionCotizacion::PENDIENTE
            ->estaPendiente()
    )->toBeTrue()
        ->and(
            EstadoRevisionCotizacion::SIN_SOLICITAR
                ->estaPendiente()
        )
        ->toBeFalse()
        ->and(
            EstadoRevisionCotizacion::APROBADA
                ->estaPendiente()
        )
        ->toBeFalse()
        ->and(
            EstadoRevisionCotizacion::RECHAZADA
                ->estaPendiente()
        )
        ->toBeFalse();
});

test('approved and rejected statuses identify completed reviews', function () {
    expect(
        EstadoRevisionCotizacion::APROBADA
            ->fueRevisada()
    )->toBeTrue()
        ->and(
            EstadoRevisionCotizacion::RECHAZADA
                ->fueRevisada()
        )
        ->toBeTrue()
        ->and(
            EstadoRevisionCotizacion::PENDIENTE
                ->fueRevisada()
        )
        ->toBeFalse()
        ->and(
            EstadoRevisionCotizacion::SIN_SOLICITAR
                ->fueRevisada()
        )
        ->toBeFalse();
});

test('quote review statuses have readable labels', function () {
    expect(
        EstadoRevisionCotizacion::SIN_SOLICITAR
            ->etiqueta()
    )->toBe('Sin solicitar')
        ->and(
            EstadoRevisionCotizacion::PENDIENTE
                ->etiqueta()
        )
        ->toBe('Pendiente')
        ->and(
            EstadoRevisionCotizacion::APROBADA
                ->etiqueta()
        )
        ->toBe('Aprobada')
        ->and(
            EstadoRevisionCotizacion::RECHAZADA
                ->etiqueta()
        )
        ->toBe('Rechazada');
});
