<?php

namespace App\Services\Pos;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\EmpresaConfiguracionFiscal;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\Facturacion\Contracts\PacConnectorInterface;
use DOMDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class FacturacionGlobalService
{
    public function __construct(
        protected PacConnectorInterface $pacConnector
    ) {}

    /**
     * @return Collection<int, Venta>
     */
    public function obtenerVentasCandidatas(Empresa $empresa, Sucursal $sucursal, Carbon $fechaInicio, Carbon $fechaFin): Collection
    {
        return Venta::query()
            ->where('empresa_id', $empresa->id)
            ->where('sucursal_id', $sucursal->id)
            ->where('estado', 'completada')
            ->where('estado_facturacion', '!=', 'facturada')
            ->whereNull('uuid_factura_global')
            ->where('es_factura_global', false)
            ->whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->with(['detalles'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param array{
     *     periodicidad: '01'|'02'|'03'|'04'|'05',
     *     meses: string,
     *     anio: int,
     *     forma_pago_predeterminada?: string
     * } $datosGlobal
     * @return array{
     *     venta_global: Venta,
     *     ventas_consolidadas: int,
     *     total: float
     * }
     */
    public function generarFacturaGlobal(
        Empresa $empresa,
        Sucursal $sucursal,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        array $datosGlobal,
        User $usuario
    ): array {
        $ventas = $this->obtenerVentasCandidatas($empresa, $sucursal, $fechaInicio, $fechaFin);

        if ($ventas->isEmpty()) {
            throw new InvalidArgumentException('No existen ventas pendientes de facturar en el periodo seleccionado.');
        }

        /** @var EmpresaConfiguracionFiscal|null $configFiscal */
        $configFiscal = EmpresaConfiguracionFiscal::query()
            ->where('empresa_id', $empresa->id)
            ->first();

        if ($configFiscal === null || ! $configFiscal->facturacion_activa) {
            throw new RuntimeException('La empresa no cuenta con configuración fiscal activa para timbrado ante el SAT.');
        }

        if ($configFiscal->timbres_restantes <= 0) {
            throw new RuntimeException('No hay timbres fiscales disponibles en la bolsa contratada.');
        }

        return DB::transaction(function () use ($empresa, $sucursal, $ventas, $datosGlobal, $configFiscal, $usuario): array {
            $totalSubtotal = 0.0;
            $totalIva = 0.0;
            $totalGeneral = 0.0;

            foreach ($ventas as $v) {
                $totalSubtotal += (float) $v->base_imponible;
                $totalIva += (float) $v->iva_total;
                $totalGeneral += (float) $v->total;
            }

            $almacen = Almacen::query()
                ->where('sucursal_id', $sucursal->id)
                ->where('activo', true)
                ->first();
            $almacenId = $almacen !== null ? (int) $almacen->id : 1;

            $folioGlobal = 'GLO-'.now()->format('Ymd').'-'.str_pad((string) (Venta::query()->where('empresa_id', $empresa->id)->where('es_factura_global', true)->count() + 1), 3, '0', STR_PAD_LEFT);

            /** @var Venta $ventaGlobal */
            $ventaGlobal = Venta::query()->create([
                'empresa_id' => $empresa->id,
                'sucursal_id' => $sucursal->id,
                'almacen_id' => $almacenId,
                'user_id' => $usuario->id,
                'folio' => $folioGlobal,
                'base_imponible' => $totalSubtotal,
                'iva_total' => $totalIva,
                'total' => $totalGeneral,
                'estado' => 'completada',
                'estado_facturacion' => 'no_facturada',
                'forma_pago_sat' => $datosGlobal['forma_pago_predeterminada'] ?? '01',
                'metodo_pago_sat' => 'PUE',
                'cliente_rfc' => 'XAXX010101000',
                'cliente_nombre' => 'PUBLICO EN GENERAL',
                'cliente_codigo_postal' => (string) ($configFiscal->codigo_postal ?: '83000'),
                'cliente_regimen_fiscal' => '616',
                'uso_cfdi_sat' => 'S01',
                'es_factura_global' => true,
                'periodicidad_global' => $datosGlobal['periodicidad'],
            ]);

            // Generar XML con desglose de tickets bajo Anexo 20 CFDI 4.0
            $xmlSinTimbrar = $this->generarXmlGlobal40($ventaGlobal, $ventas, $configFiscal, $datosGlobal);

            // Timbrado con el conector PAC
            $respuestaPac = $this->pacConnector->timbrarXml($xmlSinTimbrar, $configFiscal);

            if (! $respuestaPac['exito']) {
                $mensaje = isset($respuestaPac['mensaje_error']) ? (string) $respuestaPac['mensaje_error'] : 'Error desconocido al timbrar con el PAC.';
                throw new RuntimeException($mensaje);
            }

            $uuidFiscal = (string) ($respuestaPac['uuid'] ?? '');
            $xmlTimbrado = (string) ($respuestaPac['xml_timbrado'] ?? $xmlSinTimbrar);
            $fechaTimbrado = (string) ($respuestaPac['fecha_timbrado'] ?? now()->toIso8601String());

            $xmlPath = "cfdis/{$empresa->id}/{$uuidFiscal}.xml";
            Storage::disk('local')->put($xmlPath, $xmlTimbrado);

            $configFiscal->increment('timbres_utilizados');
            $configFiscal->decrement('timbres_restantes');

            $ventaGlobal->update([
                'estado_facturacion' => 'facturada',
                'uuid_fiscal' => $uuidFiscal,
                'serie_folio_factura' => 'GLO-'.$ventaGlobal->folio,
                'xml_path' => $xmlPath,
                'facturado_at' => $fechaTimbrado,
            ]);

            // Ligar todas las ventas al UUID de esta factura global
            $ventaIds = $ventas->pluck('id')->all();
            Venta::query()->whereIn('id', $ventaIds)->update([
                'uuid_factura_global' => $uuidFiscal,
            ]);

            return [
                'venta_global' => $ventaGlobal,
                'ventas_consolidadas' => count($ventaIds),
                'total' => $totalGeneral,
            ];
        });
    }

    /**
     * @param  Collection<int, Venta>  $ventas
     * @param array{
     *     periodicidad: '01'|'02'|'03'|'04'|'05',
     *     meses: string,
     *     anio: int,
     *     forma_pago_predeterminada?: string
     * } $datosGlobal
     */
    protected function generarXmlGlobal40(
        Venta $ventaGlobal,
        Collection $ventas,
        EmpresaConfiguracionFiscal $configFiscal,
        array $datosGlobal
    ): string {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $comprobante = $dom->createElement('cfdi:Comprobante');
        $comprobante->setAttribute('xmlns:cfdi', 'http://www.sat.gob.mx/cfd/4');
        $comprobante->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $comprobante->setAttribute('xsi:schemaLocation', 'http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd');
        $comprobante->setAttribute('Version', '4.0');
        $comprobante->setAttribute('Serie', 'GLO');
        $comprobante->setAttribute('Folio', $ventaGlobal->folio);
        $comprobante->setAttribute('Fecha', now()->format('Y-m-d\TH:i:s'));
        $comprobante->setAttribute('FormaPago', $ventaGlobal->forma_pago_sat ?: '01');
        $comprobante->setAttribute('NoCertificado', '30001000000500003416');
        $comprobante->setAttribute('SubTotal', number_format((float) $ventaGlobal->base_imponible, 2, '.', ''));
        $comprobante->setAttribute('Moneda', 'MXN');
        $comprobante->setAttribute('Total', number_format((float) $ventaGlobal->total, 2, '.', ''));
        $comprobante->setAttribute('TipoDeComprobante', 'I');
        $comprobante->setAttribute('Exportacion', '01');
        $comprobante->setAttribute('MetodoPago', 'PUE');
        $comprobante->setAttribute('LugarExpedicion', $configFiscal->codigo_postal ?: '83000');
        $comprobante->setAttribute('Sello', 'SELLO_DIGITAL_CFDI_GLOBAL');

        $dom->appendChild($comprobante);

        // Nodo InformacionGlobal
        $infoGlobal = $dom->createElement('cfdi:InformacionGlobal');
        $infoGlobal->setAttribute('Periodicidad', $datosGlobal['periodicidad']);
        $infoGlobal->setAttribute('Meses', $datosGlobal['meses']);
        $infoGlobal->setAttribute('Año', (string) $datosGlobal['anio']);
        $comprobante->appendChild($infoGlobal);

        // Nodo Emisor
        $emisor = $dom->createElement('cfdi:Emisor');
        $emisor->setAttribute('Rfc', $configFiscal->rfc ?: 'XAXX010101000');
        $emisor->setAttribute('Nombre', $configFiscal->razon_social ?: 'CANO COMPUTADORAS');
        $emisor->setAttribute('RegimenFiscal', $configFiscal->regimen_fiscal ?: '601');
        $comprobante->appendChild($emisor);

        // Nodo Receptor
        $receptor = $dom->createElement('cfdi:Receptor');
        $receptor->setAttribute('Rfc', 'XAXX010101000');
        $receptor->setAttribute('Nombre', 'PUBLICO EN GENERAL');
        $receptor->setAttribute('DomicilioFiscalReceptor', $configFiscal->codigo_postal ?: '83000');
        $receptor->setAttribute('RegimenFiscalReceptor', '616');
        $receptor->setAttribute('UsoCFDI', 'S01');
        $comprobante->appendChild($receptor);

        // Nodo Conceptos (un concepto por cada ticket individual)
        $conceptos = $dom->createElement('cfdi:Conceptos');
        foreach ($ventas as $v) {
            $subtotalTicket = (float) $v->base_imponible;
            $ivaTicket = (float) $v->iva_total;

            $concepto = $dom->createElement('cfdi:Concepto');
            $concepto->setAttribute('ClaveProdServ', '01010101');
            $concepto->setAttribute('NoIdentificacion', $v->folio);
            $concepto->setAttribute('Cantidad', '1');
            $concepto->setAttribute('ClaveUnidad', 'ACT');
            $concepto->setAttribute('Unidad', 'Actividad');
            $concepto->setAttribute('Descripcion', 'Venta');
            $concepto->setAttribute('ValorUnitario', number_format($subtotalTicket, 2, '.', ''));
            $concepto->setAttribute('Importe', number_format($subtotalTicket, 2, '.', ''));
            $concepto->setAttribute('ObjetoImp', '02');

            $impuestosConcepto = $dom->createElement('cfdi:Impuestos');
            $trasladosConcepto = $dom->createElement('cfdi:Traslados');
            $trasladoConcepto = $dom->createElement('cfdi:Traslado');
            $trasladoConcepto->setAttribute('Base', number_format($subtotalTicket, 2, '.', ''));
            $trasladoConcepto->setAttribute('Impuesto', '002');
            $trasladoConcepto->setAttribute('TipoFactor', 'Tasa');
            $trasladoConcepto->setAttribute('TasaOCuota', '0.160000');
            $trasladoConcepto->setAttribute('Importe', number_format($ivaTicket, 2, '.', ''));

            $trasladosConcepto->appendChild($trasladoConcepto);
            $impuestosConcepto->appendChild($trasladosConcepto);
            $concepto->appendChild($impuestosConcepto);

            $conceptos->appendChild($concepto);
        }
        $comprobante->appendChild($conceptos);

        // Nodo Impuestos Globales
        $impuestosGlobal = $dom->createElement('cfdi:Impuestos');
        $impuestosGlobal->setAttribute('TotalImpuestosTrasladados', number_format((float) $ventaGlobal->iva_total, 2, '.', ''));

        $trasladosGlobal = $dom->createElement('cfdi:Traslados');
        $trasladoGlobal = $dom->createElement('cfdi:Traslado');
        $trasladoGlobal->setAttribute('Base', number_format((float) $ventaGlobal->base_imponible, 2, '.', ''));
        $trasladoGlobal->setAttribute('Impuesto', '002');
        $trasladoGlobal->setAttribute('TipoFactor', 'Tasa');
        $trasladoGlobal->setAttribute('TasaOCuota', '0.160000');
        $trasladoGlobal->setAttribute('Importe', number_format((float) $ventaGlobal->iva_total, 2, '.', ''));

        $trasladosGlobal->appendChild($trasladoGlobal);
        $impuestosGlobal->appendChild($trasladosGlobal);
        $comprobante->appendChild($impuestosGlobal);

        return (string) $dom->saveXML();
    }
}
