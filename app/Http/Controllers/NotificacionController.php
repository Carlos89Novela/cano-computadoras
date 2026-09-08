<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificacionController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();

        $notificaciones = $usuario
            ->notifications()
            ->latest()
            ->paginate(10);

        $notificacionesNoLeidas = $usuario
            ->unreadNotifications()
            ->count();

        return view(
            'notificaciones.index',
            compact(
                'notificaciones',
                'notificacionesNoLeidas'
            )
        );
    }

    public function leer(
        Request $request,
        string $notificacion
    ): RedirectResponse {
        $registro = $request->user()
            ->notifications()
            ->whereKey($notificacion)
            ->firstOrFail();

        if ($registro->unread()) {
            $registro->markAsRead();
        }

        $ordenId = $registro->data['orden_id'] ?? null;

        if (
            is_numeric($ordenId)
            && $request->user()
                ->ordenesServicio()
                ->whereKey((int) $ordenId)
                ->exists()
        ) {
            return redirect()->route(
                'ordenes.show',
                [
                    'orden' => (int) $ordenId,
                ]
            );
        }

        return redirect()
            ->route('notificaciones.index');
    }

    public function leerTodas(
        Request $request
    ): RedirectResponse {
        $request->user()
            ->unreadNotifications()
            ->update([
                'read_at' => now(),
            ]);

        return redirect()
            ->route('notificaciones.index')
            ->with(
                'success',
                'Todas las notificaciones fueron marcadas como leídas.'
            );
    }
}
