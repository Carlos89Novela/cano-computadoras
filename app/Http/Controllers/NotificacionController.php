<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificacionController extends Controller
{
    public function index(Request $request): View
    {
        $notificaciones = $request->user()
            ->notifications()
            ->latest()
            ->paginate(10);

        return view(
            'notificaciones.index',
            compact('notificaciones')
        );
    }

    public function leer(
        Request $request,
        string $notificacion
    ): RedirectResponse {
        $registro = $request->user()
            ->notifications()
            ->findOrFail($notificacion);

        $registro->markAsRead();

        $ordenId = $registro->data['orden_id'] ?? null;

        if ($ordenId) {
            return redirect()->route(
                'ordenes.show',
                ['orden' => $ordenId]
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