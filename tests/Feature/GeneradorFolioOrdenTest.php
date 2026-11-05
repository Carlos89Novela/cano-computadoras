<?php

use App\Services\GeneradorFolioOrden;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('generated order folio has the expected format', function () {
    $folio = app(GeneradorFolioOrden::class)->generar();

    expect($folio)->toMatch(
        '/^REP-\d{8}-[A-Z0-9]{5}$/'
    );
});

test('generated order folios are different', function () {
    $generador = app(GeneradorFolioOrden::class);

    $folios = collect(range(1, 20))
        ->map(fn () => $generador->generar());

    expect($folios->unique())->toHaveCount(20);
});
