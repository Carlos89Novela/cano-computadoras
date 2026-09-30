<?php

namespace App\Enums;

/**
 * Enumeración para la Clasificación de Dispositivos y Equipos de Cómputo.
 *
 * Estandariza las tipologías de hardware admitidas para registro y servicio en taller:
 * - LAPTOP: Computadoras portátiles, notebooks, ultrabooks y netbooks.
 * - COMPUTADORA_ESCRITORIO: Torres o gabinetes PC tradicionales con componentes modulares.
 * - TODO_EN_UNO: Equipos All-in-One con pantalla y componentes integrados.
 * - OTRO: Dispositivos de cómputo especializados, servidores, impresoras u otros periféricos.
 */
enum TipoEquipo: string
{
    case LAPTOP = 'Laptop';

    case COMPUTADORA_ESCRITORIO = 'Computadora de escritorio';

    case TODO_EN_UNO = 'Todo en uno';

    case OTRO = 'Otro';

    /**
     * Obtiene la lista completa de valores admitidos para el tipo de equipo.
     *
     * @return array<int, string> Lista de cadenas de valores.
     */
    public static function valores(): array
    {
        return array_column(
            self::cases(),
            'value'
        );
    }
}

