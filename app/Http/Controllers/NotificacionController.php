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
        $usuario = $request->user();

        $registro = $usuario
            ->notifications()
            ->whereKey($notificacion)
            ->firstOrFail();

        if ($registro->unread()) {
            $registro->markAsRead();
        }

        $url = $registro->data['url'] ?? null;

        if (is_string($url) && $url !== '') {
            $ruta = parse_url(
                $url,
                PHP_URL_PATH
            );

            $consulta = parse_url(
                $url,
                PHP_URL_QUERY
            );

            if (is_string($ruta)) {
                $destinosPermitidos = [
                    '/supervisor/cotizaciones/',
                    '/empleado/ordenes/',
                    '/ordenes/',
                ];

                $esDestinoPermitido = collect(
                    $destinosPermitidos
                )->contains(
                    fn (string $prefijo): bool => str_starts_with($ruta, $prefijo)
                );

                if ($esDestinoPermitido) {
                    $destino = $ruta;

                    if (
                        is_string($consulta)
                        && $consulta !== ''
                    ) {
                        $destino .= '?'.$consulta;
                    }

                    return redirect()->to($destino);
                }
            }
        }

        $ordenId = $registro->data['orden_id'] ?? null;

        if (
            is_numeric($ordenId)
            && $usuario
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
            ->route('notificaciones.index')
            ->withErrors([
                'notificacion' => 'La notificación no contiene un destino disponible.',
            ]);
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
