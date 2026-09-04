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

    /**
     * Obtiene los estados utilizados como filtros rápidos.
     *
     * La clave representa el valor real almacenado en la base de datos
     * y el valor representa el texto mostrado en el botón.
     *
     * @return array<string, string>
     */
    public static function filtrosRapidos(): array
    {
        return [
            self::RECIBIDO->value => 'Recibido',
            self::EN_DIAGNOSTICO->value => 'En diagnóstico',
            self::ESPERANDO_AUTORIZACION->value => 'Esperando autorización',
            self::ESPERANDO_REFACCION->value => 'Esperando refacción',
            self::EN_REPARACION->value => 'En reparación',
            self::EN_PRUEBAS->value => 'En pruebas',
            self::LISTO_PARA_ENTREGA->value => 'Listo',
            self::ENTREGADO->value => 'Entregado',
            self::CANCELADO->value => 'Cancelado',
        ];
    }
}
