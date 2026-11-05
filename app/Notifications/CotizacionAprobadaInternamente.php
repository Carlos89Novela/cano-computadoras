<?php

namespace App\Notifications;

use App\Models\OrdenServicio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CotizacionAprobadaInternamente extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public OrdenServicio $orden
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return [
            'mail',
            'database',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(
                'Cotización aprobada '.$this->orden->folio
            )
            ->greeting(
                'Hola, '.$notifiable->name
            )
            ->line(
                'La cotización fue aprobada internamente.'
            )
            ->line(
                'Folio: '.$this->orden->folio
            )
            ->line(
                'Diagnóstico: '.$this->orden->diagnostico
            )
            ->line(
                'Costo estimado: $'.number_format(
                    (float) $this->orden->costo_estimado,
                    2
                )
            )
            ->action(
                'Consultar reparación',
                route('empleado.ordenes.show', [
                    'orden' => $this->orden->id,
                ], false)
            )
            ->line(
                'La aprobación interna no representa todavía la autorización del cliente.'
            );
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo' => 'cotizacion_aprobada_internamente',
            'orden_id' => $this->orden->id,
            'folio' => $this->orden->folio,
            'estado_revision' => $this->orden->estado_revision_cotizacion->value,
            'mensaje' => 'La cotización '.$this->orden->folio
                .' fue aprobada internamente.',
            'url' => route('empleado.ordenes.show', [
                'orden' => $this->orden->id,
            ], false),
        ];
    }
}
