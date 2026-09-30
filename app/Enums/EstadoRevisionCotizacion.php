<?php

namespace App\Enums;

/**
 * Enumeración para el Control de Calidad y Revisión de Cotizaciones.
 *
 * Modela el flujo de validación de diagnósticos y presupuestos entre técnicos y supervisores:
 * - SIN_SOLICITAR: Estado inicial cuando la orden ingresa y el técnico aún investiga la falla.
 * - PENDIENTE: El técnico ha formulado diagnóstico y costo y solicitó la revisión formal del supervisor.
 * - APROBADA: El supervisor validó y aprobó el presupuesto, habilitando la presentación al cliente.
 * - RECHAZADA: El supervisor detectó incongruencias y devolvió la cotización con observaciones al técnico.
 */
enum EstadoRevisionCotizacion: string
{
    case SIN_SOLICITAR = 'sin_solicitar';

    case PENDIENTE = 'pendiente';

    case APROBADA = 'aprobada';

    case RECHAZADA = 'rechazada';

    /**
     * Obtiene todos los valores del enum.
     *
     * @return array<int, string> Lista de valores de estados de revisión.
     */
    public static function valores(): array
    {
        return array_column(
            self::cases(),
            'value'
        );
    }

    /**
     * Retorna la etiqueta visual legible para interfaces de usuario.
     *
     * @return string Etiqueta en lenguaje natural.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::SIN_SOLICITAR => 'Sin solicitar',
            self::PENDIENTE => 'Pendiente',
            self::APROBADA => 'Aprobada',
            self::RECHAZADA => 'Rechazada',
        };
    }

    /**
     * Determina si la cotización se encuentra en espera de decisión por parte de un supervisor.
     *
     * @return bool Verdadero si está en revisión pendiente.
     */
    public function estaPendiente(): bool
    {
        return $this === self::PENDIENTE;
    }

    /**
     * Determina si la cotización ya fue objeto de una decisión previa (aprobada o rechazada).
     *
     * @return bool Verdadero si ya fue dictaminada por supervisión.
     */
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

