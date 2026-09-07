<?php

namespace App\Enums;

enum EstadoAutorizacion: string
{
    case PENDIENTE = 'pendiente';

    case AUTORIZADA = 'autorizada';

    case RECHAZADA = 'rechazada';

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function decisiones(): array
    {
        return [
            self::AUTORIZADA->value,
            self::RECHAZADA->value,
        ];
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::AUTORIZADA => 'Autorizada',
            self::RECHAZADA => 'Rechazada',
        };
    }
}
