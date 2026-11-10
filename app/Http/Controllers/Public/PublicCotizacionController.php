<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\OrdenServicio;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\Str;

class PublicCotizacionController extends Controller
{
    /**
     * Muestra la cotización técnica del equipo al cliente final mediante enlace seguro.
     */
    public function show(string $token): View
    {
        /** @var OrdenServicio $orden */
        $orden = OrdenServicio::query()
            ->where('token_publico', $token)
            ->with(['equipo', 'user', 'reservasInventario.producto'])
            ->firstOrFail();

        $expirada = $orden->cotizacion_expira_at !== null
            && Carbon::parse((string) $orden->cotizacion_expira_at)->isPast();

        $yaFirmada = $orden->firmado_at !== null
            || in_array((string) $orden->estado, ['autorizada', 'en_reparacion', 'lista_para_entrega', 'entregada'], true);

        return ViewFacade::make('public.cotizacion', [
            'orden' => $orden,
            'expirada' => $expirada,
            'yaFirmada' => $yaFirmada,
            'rechazada' => (string) $orden->estado === 'rechazada',
        ]);
    }

    /**
     * Autoriza la cotización capturando y almacenando la firma digital en lienzo HTML5 Canvas.
     */
    public function autorizar(Request $request, string $token): JsonResponse
    {
        /** @var OrdenServicio $orden */
        $orden = OrdenServicio::query()
            ->where('token_publico', $token)
            ->firstOrFail();

        if ((string) $orden->estado === 'rechazada') {
            return response()->json(['message' => 'Esta cotización fue rechazada previamente.'], 422);
        }

        if ($orden->cotizacion_expira_at !== null && Carbon::parse((string) $orden->cotizacion_expira_at)->isPast()) {
            return response()->json(['message' => 'El presupuesto ha expirado. Contacte al taller para una nueva evaluación.'], 422);
        }

        if ($orden->firmado_at !== null || in_array((string) $orden->estado, ['autorizada', 'en_reparacion', 'lista_para_entrega', 'entregada'], true)) {
            return response()->json(['message' => 'Esta orden ya cuenta con autorización previa.'], 422);
        }

        $validated = $request->validate([
            'firma_base64' => ['required', 'string'],
        ]);

        $base64Data = (string) $validated['firma_base64'];

        if (str_contains($base64Data, ';base64,')) {
            $partes = explode(';base64,', $base64Data);
            $base64Raw = end($partes);
        } else {
            $base64Raw = $base64Data;
        }

        $imagenBinaria = base64_decode((string) $base64Raw, true);

        if ($imagenBinaria === false) {
            return response()->json(['message' => 'Formato de imagen de firma digital no válido.'], 422);
        }

        $rutaFirma = "firmas_cotizaciones/{$orden->id}-".Str::random(12).'.png';
        Storage::disk('local')->put($rutaFirma, $imagenBinaria);

        $ahora = now();

        DB::transaction(function () use ($orden, $rutaFirma, $ahora): void {
            $orden->update([
                'estado' => 'autorizada',
                'firma_digital_path' => $rutaFirma,
                'firmado_at' => $ahora,
            ]);

            if (method_exists($orden, 'historialReparaciones')) {
                $orden->historialReparaciones()->create([
                    'estado' => 'autorizada',
                    'comentarios' => 'Cotización aprobada por el cliente mediante firma digital en portal web.',
                    'user_id' => $orden->user_id,
                ]);
            }
        });

        $ordenActualizada = $orden->fresh();
        $firmadoAtString = $ordenActualizada->firmado_at !== null
            ? Carbon::parse((string) $ordenActualizada->firmado_at)->toIso8601String()
            : $ahora->toIso8601String();

        return response()->json([
            'message' => '¡Cotización autorizada con éxito! Nuestro equipo técnico comenzará la reparación de su equipo.',
            'estado' => 'autorizada',
            'firmado_at' => $firmadoAtString,
        ]);
    }

    /**
     * Registra el rechazo formal de la cotización técnica.
     */
    public function rechazar(Request $request, string $token): JsonResponse
    {
        /** @var OrdenServicio $orden */
        $orden = OrdenServicio::query()
            ->where('token_publico', $token)
            ->firstOrFail();

        if (in_array((string) $orden->estado, ['autorizada', 'en_reparacion', 'lista_para_entrega', 'entregada'], true)) {
            return response()->json(['message' => 'No es posible rechazar una orden que ya fue autorizada o está en proceso.'], 422);
        }

        $validated = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($orden, $validated): void {
            $orden->update([
                'estado' => 'rechazada',
                'motivo_rechazo' => (string) $validated['motivo_rechazo'],
            ]);

            if (method_exists($orden, 'historialReparaciones')) {
                $orden->historialReparaciones()->create([
                    'estado' => 'rechazada',
                    'comentarios' => 'Cotización rechazada por el cliente. Motivo: '.(string) $validated['motivo_rechazo'],
                    'user_id' => $orden->user_id,
                ]);
            }
        });

        return response()->json([
            'message' => 'La cotización ha sido declinada. Su equipo quedará disponible para retiro.',
            'estado' => 'rechazada',
        ]);
    }
}
