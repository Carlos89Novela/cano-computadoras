<?php

namespace App\Services;

use App\Models\OrdenServicio;
use Illuminate\Support\Str;

class GeneradorFolioOrden
{
    public function generar(): string
    {
        do {
            $folio = 'REP-'
                .now()->format('Ymd')
                .'-'
                .Str::upper(Str::random(5));
        } while (
            OrdenServicio::query()
                ->where('folio', $folio)
                ->exists()
        );

        return $folio;
    }
}
