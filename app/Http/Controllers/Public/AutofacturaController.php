<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Venta;
use App\Services\Facturacion\TimbrarVentaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFacade;
use Throwable;

class AutofacturaController extends Controller
{
    public function __construct(
        protected TimbrarVentaService $timbrarVentaService
    ) {}

    /**
     * Muestra el formulario público para que el cliente ingrese su RFC y datos fiscales.
     */
    public function show(string $token): View
    {
        /** @var Venta $venta */
        $venta = Venta::query()
            ->where('token_autofactura', $token)
            ->with(['empresa', 'detalles'])
            ->firstOrFail();

        $yaFacturada = $venta->estado_facturacion === 'facturada';
        $expirada = $venta->autofactura_limite_at !== null && $venta->autofactura_limite_at->isPast();

        return ViewFacade::make('public.autofactura', [
            'venta' => $venta,
            'yaFacturada' => $yaFacturada,
            'expirada' => $expirada,
        ]);
    }

    /**
     * Procesa y timbra la factura CFDI 4.0 directamente solicitada por el cliente.
     */
    public function procesar(Request $request, string $token): JsonResponse
    {
        /** @var Venta $venta */
        $venta = Venta::query()
            ->where('token_autofactura', $token)
            ->firstOrFail();

        if ($venta->estado_facturacion === 'facturada') {
            return response()->json(['message' => 'Esta nota de consumo ya ha sido timbrada previamente.'], 422);
        }

        if ($venta->autofactura_limite_at !== null && $venta->autofactura_limite_at->isPast()) {
            return response()->json(['message' => 'El plazo para autofacturar este ticket ha expirado.'], 422);
        }

        /** @var array{
         *     rfc: string,
         *     razon_social: string,
         *     codigo_postal: string,
         *     regimen_fiscal: string,
         *     uso_cfdi: string,
         *     correo: string
         * } $validated */
        $validated = $request->validate([
            'rfc' => ['required', 'string', 'min:12', 'max:13'],
            'razon_social' => ['required', 'string', 'max:200'],
            'codigo_postal' => ['required', 'string', 'size:5'],
            'regimen_fiscal' => ['required', 'string', 'size:3'],
            'uso_cfdi' => ['required', 'string', 'size:3'],
            'correo' => ['required', 'email', 'max:100'],
        ]);

        $venta->update([
            'cliente_rfc' => strtoupper($validated['rfc']),
            'cliente_nombre' => strtoupper($validated['razon_social']),
            'cliente_codigo_postal' => $validated['codigo_postal'],
            'cliente_regimen_fiscal' => $validated['regimen_fiscal'],
            'uso_cfdi_sat' => $validated['uso_cfdi'],
            'cliente_email' => $validated['correo'],
        ]);

        try {
            $ventaTimbrada = $this->timbrarVentaService->ejecutar($venta);

            return response()->json([
                'message' => '¡Factura generada y timbrada ante el SAT con éxito!',
                'uuid_fiscal' => $ventaTimbrada->uuid_fiscal,
                'descargar_xml' => route('facturacion.ventas.descargar-xml', $ventaTimbrada->id),
                'descargar_pdf' => route('facturacion.ventas.descargar-pdf', $ventaTimbrada->id),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'No fue posible timbrar: '.$e->getMessage(),
            ], 422);
        }
    }
}
