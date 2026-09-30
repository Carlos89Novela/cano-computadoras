<?php

namespace App\Enums;

/**
 * Enumeración para el Estado de Autorización de Presupuestos por el Cliente.
 *
 * Modela las posibles decisiones del cliente frente al presupuesto propuesto:
 * - PENDIENTE: El presupuesto ha sido aprobado por supervisión y espera la respuesta del cliente.
 * - AUTORIZADA: El cliente aceptó el presupuesto y autoriza el inicio de reparaciones y compra de refacciones.
 * - RECHAZADA: El cliente declinó la cotización, procediendo al cierre o devolución sin reparación.
 */
enum EstadoAutorizacion: string
{
    case PENDIENTE = 'pendiente';

    case AUTORIZADA = 'autorizada';

    case RECHAZADA = 'rechazada';

    /**
     * Obtiene la lista completa de valores del enum.
     *
     * @return array<int, string> Lista de cadenas de valores admitidos.
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Obtiene los valores que representan una decisión explícita tomada por el cliente.
     *
     * @return array<int, string> Lista de decisiones ('autorizada', 'rechazada').
     */
    public static function decisiones(): array
    {
        return [
            self::AUTORIZADA->value,
            self::RECHAZADA->value,
        ];
    }

    /**
     * Retorna la etiqueta visual legible para interfaces de usuario.
     *
     * @return string Etiqueta en lenguaje natural.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::AUTORIZADA => 'Autorizada',
            self::RECHAZADA => 'Rechazada',
        };
    }
}

