<?php

namespace App\Notifications;

use App\Models\OrdenServicio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PresupuestoAutorizadoPorCliente extends Notification implements ShouldQueue
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
                'Presupuesto autorizado '.$this->orden->folio
            )
            ->greeting(
                'Hola, '.$notifiable->name
            )
            ->line(
                'El cliente autorizó el presupuesto de la reparación.'
            )
            ->line(
                'Folio: '.$this->orden->folio
            )
            ->line(
                'Costo estimado: $'.number_format(
                    (float) $this->orden->costo_estimado,
                    2
                )
            )
            ->line(
                'La orden quedó en estado: '.$this->orden->estado
            )
            ->action(
                'Consultar reparación',
                $this->urlAbsoluta($notifiable)
            )
            ->line(
                'La decisión del cliente quedó registrada en el historial.'
            );
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo' => 'presupuesto_autorizado_por_cliente',
            'orden_id' => $this->orden->id,
            'folio' => $this->orden->folio,
            'estado' => $this->orden->estado,
            'autorizacion' => $this->orden->autorizacion,
            'mensaje' => 'El cliente autorizó el presupuesto de la reparación '
                .$this->orden->folio.'.',
            'url' => $this->urlRelativa($notifiable),
        ];
    }

    private function urlAbsoluta(object $notifiable): string
    {
        if ($notifiable->hasRole('empleado')) {
            return route('empleado.ordenes.show', [
                'orden' => $this->orden->id,
            ]);
        }

        return route('supervisor.dashboard');
    }

    private function urlRelativa(object $notifiable): string
    {
        if ($notifiable->hasRole('empleado')) {
            return route(
                'empleado.ordenes.show',
                [
                    'orden' => $this->orden->id,
                ],
                false
            );
        }

        return route(
            'supervisor.dashboard',
            [],
            false
        );
    }
}
