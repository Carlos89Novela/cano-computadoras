<?php

namespace App\Notifications;

use App\Models\OrdenServicio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CotizacionRechazadaInternamente extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public OrdenServicio $orden,
        public string $observacion
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
                'Cotización devuelta '.$this->orden->folio
            )
            ->greeting(
                'Hola, '.$notifiable->name
            )
            ->line(
                'La cotización fue devuelta para corrección.'
            )
            ->line(
                'Folio: '.$this->orden->folio
            )
            ->line(
                'Motivo: '.$this->observacion
            )
            ->action(
                'Corregir cotización',
                route('empleado.ordenes.show', [
                    'orden' => $this->orden->id,
                ], false)
            )
            ->line(
                'Corrige el diagnóstico o el costo estimado y solicita nuevamente la revisión.'
            );
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo' => 'cotizacion_rechazada_internamente',
            'orden_id' => $this->orden->id,
            'folio' => $this->orden->folio,
            'estado_revision' => $this->orden->estado_revision_cotizacion->value,
            'observacion' => $this->observacion,
            'mensaje' => 'La cotización '.$this->orden->folio
                .' fue devuelta para corrección.',
            'url' => route('empleado.ordenes.show', [
                'orden' => $this->orden->id,
            ], false),
        ];
    }
}
