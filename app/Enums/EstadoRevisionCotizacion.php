<?php

namespace App\Enums;

enum EstadoRevisionCotizacion: string
{
    case SIN_SOLICITAR = 'sin_solicitar';

    case PENDIENTE = 'pendiente';

    case APROBADA = 'aprobada';

    case RECHAZADA = 'rechazada';

    public static function valores(): array
    {
        return array_column(
            self::cases(),
            'value'
        );
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::SIN_SOLICITAR => 'Sin solicitar',
            self::PENDIENTE => 'Pendiente',
            self::APROBADA => 'Aprobada',
            self::RECHAZADA => 'Rechazada',
        };
    }

    public function estaPendiente(): bool
    {
        return $this === self::PENDIENTE;
    }

    public function fueRevisada(): bool
    {
        return in_array(
            $this,
            [
                self::APROBADA,
                self::RECHAZADA,
            ],
            true
        );
    }
}
