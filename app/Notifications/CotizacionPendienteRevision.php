<?php

namespace App\Notifications;

use App\Models\OrdenServicio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CotizacionPendienteRevision extends Notification implements ShouldQueue
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
                'Cotización pendiente '.$this->orden->folio
            )
            ->greeting(
                'Hola, '.$notifiable->name
            )
            ->line(
                'Se recibió una nueva cotización pendiente de revisión.'
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
                'Revisar cotización',
                route('supervisor.cotizaciones.show', [
                    'orden' => $this->orden->id,
                ], false)
            )
            ->line(
                'La cotización debe revisarse antes de presentarla al cliente.'
            );
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo' => 'cotizacion_pendiente_revision',
            'orden_id' => $this->orden->id,
            'folio' => $this->orden->folio,
            'estado_revision' => $this->orden->estado_revision_cotizacion->value,
            'mensaje' => 'La cotización '.$this->orden->folio
                .' está pendiente de revisión.',
            'url' => route('supervisor.cotizaciones.show', [
                'orden' => $this->orden->id,
            ], false),
        ];
    }
}
