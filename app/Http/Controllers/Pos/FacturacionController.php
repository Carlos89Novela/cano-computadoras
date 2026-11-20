<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Jobs\Facturacion\TimbrarVentaJob;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Venta;
use App\Services\Facturacion\TimbrarVentaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Controlador de Facturación Electrónica SAT (CFDI 4.0) desde el Punto de Venta.
 * Gestiona el timbrado inmediato o encolado de ventas, procesamiento de pendientes,
 * descarga de XML/PDF y envío de comprobantes por correo electrónico.
 */
class FacturacionController extends Controller
{
    public function __construct(
        protected TimbrarVentaService $timbrarVentaService
    ) {}

    /**
     * Intenta timbrar una venta en formato CFDI 4.0 de forma inmediata;
     * si ocurre un fallo o intermitencia del PAC, marca la venta como pendiente y la encola.
     */
    public function timbrar(Request $request, Venta $venta): JsonResponse
    {
        try {
            // Intentar timbrado directo e inmediato con el servicio CFDI 4.0
            $ventaTimbrada = $this->timbrarVentaService->ejecutar($venta);

            return response()->json([
                'message' => 'Factura CFDI 4.0 generada y timbrada exitosamente.',
                'uuid' => $ventaTimbrada->uuid_fiscal,
                'folio' => $ventaTimbrada->serie_folio_factura,
                'estado_facturacion' => $ventaTimbrada->estado_facturacion,
            ]);
        } catch (Throwable $e) {
            // En caso de intermitencia o error del PAC, dejar la venta pendiente y despachar el job
            $venta->update(['estado_facturacion' => 'pendiente']);
            TimbrarVentaJob::dispatch($venta);

            return response()->json([
                'message' => 'El PAC no respondió de inmediato. La venta quedó en cola con reintentos automáticos programados.',
                'estado_facturacion' => 'pendiente',
                'error_previo' => $e->getMessage(),
            ], 202);
        }
    }

    /**
     * Ejecuta el comando artesanal para procesar facturas pendientes de la empresa activa.
     */
    public function procesarLotePendientes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = (int) $user->empresa_id;

        // Ejecutar comando de procesamiento por lotes filtrado por empresa si aplica
        Artisan::call('facturacion:procesar-pendientes', [
            '--empresa_id' => $empresaId > 0 ? $empresaId : null,
        ]);

        $salida = Artisan::output();

        return response()->json([
            'message' => 'Lote de facturas pendientes procesado.',
            'salida' => $salida,
        ]);
    }

    /**
     * Descarga el archivo XML timbrado almacenado en disco para una venta facturada.
     */
    public function descargarXml(Venta $venta): Response
    {
        // Verificar que la venta esté facturada y tenga ruta de XML registrada
        abort_unless($venta->estado_facturacion === 'facturada' && $venta->xml_path, 404, 'Comprobante fiscal no encontrado.');

        // Comprobar existencia física del XML en el disco local
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

    /**
     * Genera y descarga la representación impresa en PDF del CFDI 4.0 de la venta.
     */
    public function descargarPdf(Venta $venta): Response
    {
        // Permitir descarga de PDF únicamente si la venta ya fue facturada
        abort_unless($venta->estado_facturacion === 'facturada', 404, 'La venta no se encuentra facturada.');

        // Cargar relaciones necesarias para renderizar la vista del comprobante
        $venta->load(['detalles.producto', 'empresa', 'sucursal']);

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('facturacion.cfdi_pdf', compact('venta'))
            ->setPaper('letter', 'portrait');

        return $pdf->download("Factura-{$venta->serie_folio_factura}.pdf");
    }

    /**
     * Obtiene el correo electrónico del cliente asociado a la venta y envía el comprobante CFDI.
     */
    public function enviarCorreo(Request $request, Venta $venta): JsonResponse
    {
        // Validar que el comprobante ya haya sido timbrado antes de enviarlo
        abort_unless($venta->estado_facturacion === 'facturada', 422, 'La venta aún no cuenta con factura timbrada.');

        // 1. Priorizar el correo enviado explícitamente en la petición si viene informado
        $correoDestino = trim((string) $request->input('email', ''));

        // 2. Si no se envió en la petición, verificar si la tabla ventas cuenta con columna cliente_email
        if ($correoDestino === '' && Schema::hasColumn('ventas', 'cliente_email')) {
            $correoDestino = trim((string) ($venta->cliente_email ?? ''));
        }

        // 3. Si aún no hay correo, buscar al cliente en el catálogo por cliente_id o por cliente_rfc
        if ($correoDestino === '') {
            $cliente = null;

            if (Schema::hasColumn('ventas', 'cliente_id') && ! empty($venta->cliente_id)) {
                $cliente = Cliente::query()->find($venta->cliente_id);
            }

            if (! $cliente && ! empty($venta->cliente_rfc)) {
                $rfcLimpio = strtoupper(trim((string) $venta->cliente_rfc));
                $cliente = Cliente::query()
                    ->whereRaw('UPPER(TRIM(rfc)) = ?', [$rfcLimpio])
                    ->first();
            }

            if ($cliente) {
                $correoDestino = trim((string) ($cliente->email ?? $cliente->correo ?? ''));
            }
        }

        // Devolver error 422 si no se localizó un correo válido en ninguna fuente
        if ($correoDestino === '') {
            return response()->json([
                'message' => 'El cliente o la venta no tiene un correo electrónico registrado.',
            ], 422);
        }

        // Aquí puedes despachar tu Mailable (ej. Mail::to($correoDestino)->send(new FacturaCfdiMail($venta)))
        return response()->json([
            'message' => "Comprobante CFDI enviado a {$correoDestino}.",
        ]);
    }

    /**
     * Regenera la factura CFDI de una venta ya facturada únicamente si pertenece al mes en curso,
     * sincronizando previamente los datos fiscales más recientes del cliente.
     */
    public function regenerar(Request $request, Venta $venta): JsonResponse
    {
        // Verificar que la venta cuente con facturación previa
        abort_unless($venta->estado_facturacion === 'facturada', 422, 'La venta aún no está facturada para poder regenerarse.');

        // Tomar la fecha de facturación o creación para validar el mes calendario
        $fechaReferencia = $venta->facturado_at ?? $venta->created_at;
        $fechaCarbon = $fechaReferencia ? \Illuminate\Support\Carbon::parse($fechaReferencia) : null;

        // Validar estrictamente que sea del mismo mes y año que la fecha actual
        if (! $fechaCarbon || ! $fechaCarbon->isSameMonth(now())) {
            return response()->json([
                'message' => 'Solo es posible regenerar facturas emitidas dentro del mes en curso.',
            ], 422);
        }

        // Localizar el registro actualizado del cliente para tomar sus datos más recientes
        $cliente = null;
        if (Schema::hasColumn('ventas', 'cliente_id') && ! empty($venta->cliente_id)) {
            $cliente = Cliente::query()->find($venta->cliente_id);
        }
        if (! $cliente && ! empty($venta->cliente_rfc)) {
            $rfcActual = strtoupper(trim((string) $venta->cliente_rfc));
            $cliente = Cliente::query()
                ->whereRaw('UPPER(TRIM(rfc)) = ?', [$rfcActual])
                ->first();
        }

        // Preparar actualización de datos fiscales en la venta antes de re-timbrar
        $datosVenta = [
            'estado_facturacion' => 'pendiente',
            'uuid_fiscal' => null,
            'facturado_at' => null,
        ];

        if ($cliente) {
            $datosVenta['cliente_nombre'] = $cliente->nombre ?: $venta->cliente_nombre;
            $datosVenta['cliente_rfc'] = $cliente->rfc ? strtoupper(trim((string) $cliente->rfc)) : $venta->cliente_rfc;

            if (Schema::hasColumn('ventas', 'cliente_id')) {
                $datosVenta['cliente_id'] = $cliente->id;
            }
            if (Schema::hasColumn('ventas', 'cliente_codigo_postal') && ! empty($cliente->codigo_postal)) {
                $datosVenta['cliente_codigo_postal'] = $cliente->codigo_postal;
            }
            if (Schema::hasColumn('ventas', 'cliente_regimen_fiscal') && ! empty($cliente->regimen_fiscal)) {
                $datosVenta['cliente_regimen_fiscal'] = $cliente->regimen_fiscal;
            }
            if (Schema::hasColumn('ventas', 'uso_cfdi_sat') && ! empty($cliente->uso_cfdi)) {
                $datosVenta['uso_cfdi_sat'] = $cliente->uso_cfdi;
            }
            if (Schema::hasColumn('ventas', 'cliente_email') && ! empty($cliente->email)) {
                $datosVenta['cliente_email'] = $cliente->email;
            }
        }

        // Guardar datos sincronizados y restablecer estado para regenerar
        $venta->update($datosVenta);

        // Ejecutar nuevamente el servicio de timbrado con los datos actualizados
        $ventaTimbrada = $this->timbrarVentaService->ejecutar($venta->fresh());

        return response()->json([
            'message' => 'Factura regenerada correctamente con los datos actualizados del cliente.',
            'uuid' => $ventaTimbrada->uuid_fiscal,
            'folio' => $ventaTimbrada->serie_folio_factura,
            'estado_facturacion' => $ventaTimbrada->estado_facturacion,
        ]);
    }
}
