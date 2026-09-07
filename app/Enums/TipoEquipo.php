<?php

namespace App\Enums;

enum TipoEquipo: string
{
    case LAPTOP = 'Laptop';

    case COMPUTADORA_ESCRITORIO = 'Computadora de escritorio';

    case TODO_EN_UNO = 'Todo en uno';

    case OTRO = 'Otro';

    public static function valores(): array
    {
        return array_column(
            self::cases(),
            'value'
        );
    }
}
