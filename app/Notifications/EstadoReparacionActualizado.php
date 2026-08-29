<?php

namespace App\Notifications;

use App\Models\OrdenServicio;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EstadoReparacionActualizado extends Notification
{
    use Queueable;

    public function __construct(
        public OrdenServicio $orden,
        public ?string $comentario = null
    ) {
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
                'Actualización de reparación '.$this->orden->folio
            )
            ->greeting(
                'Hola, '.$notifiable->name
            )
            ->line(
                'La reparación de tu equipo ha sido actualizada.'
            )
            ->line(
                'Folio: '.$this->orden->folio
            )
            ->line(
                'Estado actual: '.$this->orden->estado
            )
            ->when(
                !empty($this->comentario),
                function (MailMessage $mensaje) {
                    return $mensaje->line(
                        'Comentario: '.$this->comentario
                    );
                }
            )
            ->action(
                'Consultar reparación',
                route('ordenes.show', [
                    'orden' => $this->orden->id,
                ])
            )
            ->line(
                'Gracias por confiar en Cano Computadoras.'
            );
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'orden_id' => $this->orden->id,
            'folio' => $this->orden->folio,
            'estado' => $this->orden->estado,
            'comentario' => $this->comentario,
            'mensaje' => 'Tu reparación fue actualizada a: '
                .$this->orden->estado,
        ];
    }
}