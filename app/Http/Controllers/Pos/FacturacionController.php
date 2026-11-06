<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Jobs\Facturacion\TimbrarVentaJob;
use App\Models\User;
use App\Models\Venta;
use App\Services\Facturacion\TimbrarVentaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class FacturacionController extends Controller
{
    public function __construct(
        protected TimbrarVentaService $timbrarVentaService
    ) {}

    public function timbrar(Request $request, Venta $venta): JsonResponse
    {
        try {
            // Intento directo inmediato
            $ventaTimbrada = $this->timbrarVentaService->ejecutar($venta);

            return response()->json([
                'message' => 'Factura CFDI 4.0 generada y timbrada exitosamente.',
                'uuid' => $ventaTimbrada->uuid_fiscal,
                'folio' => $ventaTimbrada->serie_folio_factura,
                'estado_facturacion' => $ventaTimbrada->estado_facturacion,
            ]);
        } catch (Throwable $e) {
            // Si el PAC falla o hay intermitencia, se encola con reintentos automáticos
            $venta->update(['estado_facturacion' => 'pendiente']);
            TimbrarVentaJob::dispatch($venta);

            return response()->json([
                'message' => 'El PAC no respondió de inmediato. La venta quedó en cola con reintentos automáticos programados.',
                'estado_facturacion' => 'pendiente',
                'error_previo' => $e->getMessage(),
            ], 202);
        }
    }

    public function procesarLotePendientes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = (int) $user->empresa_id;

        Artisan::call('facturacion:procesar-pendientes', [
            '--empresa_id' => $empresaId > 0 ? $empresaId : null,
        ]);

        $salida = Artisan::output();

        return response()->json([
            'message' => 'Lote de facturas pendientes procesado.',
            'salida' => $salida,
        ]);
    }

    public function descargarXml(Venta $venta): Response
    {
        abort_unless($venta->estado_facturacion === 'facturada' && $venta->xml_path, 404, 'Comprobante fiscal no encontrado.');

        if (! Storage::disk('local')->exists((string) $venta->xml_path)) {
            abort(404, 'El archivo XML no existe en el almacenamiento.');
        }

        /** @var string $contenido */
        $contenido = Storage::disk('local')->get((string) $venta->xml_path);

        return response($contenido, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"Factura-{$venta->uuid_fiscal}.xml\"",
        ]);
    }

    public function descargarPdf(Venta $venta): Response
    {
        abort_unless($venta->estado_facturacion === 'facturada', 404, 'La venta no se encuentra facturada.');

        $venta->load(['detalles.producto', 'empresa', 'sucursal']);

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('facturacion.cfdi_pdf', compact('venta'))
            ->setPaper('letter', 'portrait');

        return $pdf->download("Factura-{$venta->serie_folio_factura}.pdf");
    }
}
