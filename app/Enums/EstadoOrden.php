<?php

namespace App\Enums;

enum EstadoOrden: string
{
    case RECIBIDO = 'Recibido';

    case EN_DIAGNOSTICO = 'En diagnóstico';

    case ESPERANDO_AUTORIZACION = 'Esperando autorización';

    case ESPERANDO_REFACCION = 'Esperando refacción';

    case EN_REPARACION = 'En reparación';

    case EN_PRUEBAS = 'En pruebas';

    case LISTO_PARA_ENTREGA = 'Listo para entrega';

    case ENTREGADO = 'Entregado';

    case CANCELADO = 'Cancelado';

    /**
     * Obtiene todos los valores permitidos.
     *
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Obtiene los estados que representan una orden finalizada.
     *
     * @return array<int, string>
     */
    public static function finalizados(): array
    {
        return [
            self::ENTREGADO->value,
            self::CANCELADO->value,
        ];
    }
}
