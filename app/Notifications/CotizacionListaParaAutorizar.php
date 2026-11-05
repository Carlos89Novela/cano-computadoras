<?php

namespace App\Notifications;

use App\Models\OrdenServicio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CotizacionListaParaAutorizar extends Notification implements ShouldQueue
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
                'Presupuesto disponible '.$this->orden->folio
            )
            ->greeting(
                'Hola, '.$notifiable->name
            )
            ->line(
                'El presupuesto de la reparación está listo para tu revisión.'
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
            ->line(
                'Consulta la reparación para autorizar o rechazar el presupuesto.'
            )
            ->action(
                'Revisar presupuesto',
                route('ordenes.show', [
                    'orden' => $this->orden->id,
                ])
            )
            ->line(
                'No se iniciarán los trabajos sujetos a presupuesto hasta recibir tu decisión.'
            );
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo' => 'cotizacion_lista_para_autorizar',
            'orden_id' => $this->orden->id,
            'folio' => $this->orden->folio,
            'estado' => $this->orden->estado,
            'estado_revision' => $this->orden->estado_revision_cotizacion->value,
            'autorizacion' => $this->orden->autorizacion,
            'mensaje' => 'El presupuesto de la reparación '
                .$this->orden->folio
                .' está listo para tu decisión.',
            'url' => route(
                'ordenes.show',
                [
                    'orden' => $this->orden->id,
                ],
                false
            ),
        ];
    }
}
