<?php

namespace App\Services\Facturacion;

use App\Models\Empresa;
use App\Models\EmpresaConfiguracionFiscal;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\Facturacion\Contracts\PacConnectorInterface;
use DOMDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TimbrarVentaService
{
    public function __construct(
        protected PacConnectorInterface $pacConnector
    ) {}

    public function ejecutar(Venta $venta): Venta
    {
        return DB::transaction(function () use ($venta): Venta {
            /** @var Venta $ventaBloqueada */
            $ventaBloqueada = Venta::query()
                ->lockForUpdate()
                ->with(['detalles.producto', 'empresa', 'sucursal'])
                ->findOrFail($venta->id);

            if ($ventaBloqueada->estado !== 'completada') {
                throw ValidationException::withMessages([
                    'venta' => 'Solo se pueden timbrar ventas en estado completada.',
                ]);
            }

            if ($ventaBloqueada->estado_facturacion === 'facturada') {
                throw ValidationException::withMessages([
                    'venta' => "Esta venta ya fue timbrada previamente (UUID: {$ventaBloqueada->uuid_fiscal}).",
                ]);
            }

            /** @var Empresa $empresa */
            $empresa = $ventaBloqueada->empresa;

            /** @var EmpresaConfiguracionFiscal|null $configFiscal */
            $configFiscal = EmpresaConfiguracionFiscal::query()
                ->where('empresa_id', $empresa->id)
                ->first();

            if (! $configFiscal || ! $configFiscal->facturacion_activa) {
                throw ValidationException::withMessages([
                    'empresa' => 'La empresa no cuenta con la configuración fiscal activa para facturación electrónica.',
                ]);
            }

            if ($configFiscal->timbres_restantes <= 0) {
                throw ValidationException::withMessages([
                    'timbres' => 'No hay timbres fiscales disponibles en la bolsa contratada.',
                ]);
            }

            // 1. Construir XML CFDI 4.0
            $xmlSinTimbrar = $this->generarXmlCfdi40($ventaBloqueada, $configFiscal);

            // 2. Timbrar mediante conector PAC
            $respuestaPac = $this->pacConnector->timbrarXml($xmlSinTimbrar, $configFiscal);

            if (! $respuestaPac['exito']) {
                $mensaje = isset($respuestaPac['mensaje_error']) ? $respuestaPac['mensaje_error'] : 'Error desconocido al timbrar con el PAC.';
                throw ValidationException::withMessages(['pac' => $mensaje]);
            }

            $uuidFiscal = (string) ($respuestaPac['uuid'] ?? '');
            $xmlTimbrado = (string) ($respuestaPac['xml_timbrado'] ?? $xmlSinTimbrar);
            $fechaTimbrado = (string) ($respuestaPac['fecha_timbrado'] ?? now()->toIso8601String());

            // 3. Guardar archivo XML en storage local
            $xmlPath = "cfdis/{$empresa->id}/{$uuidFiscal}.xml";
            Storage::disk('local')->put($xmlPath, $xmlTimbrado);

            // 4. Actualizar saldos de timbres y estado de venta
            $configFiscal->increment('timbres_utilizados');
            $configFiscal->decrement('timbres_restantes');

            $ventaBloqueada->update([
                'estado_facturacion' => 'facturada',
                'uuid_fiscal' => $uuidFiscal,
                'serie_folio_factura' => 'POS-'.$ventaBloqueada->folio,
                'xml_path' => $xmlPath,
                'facturado_at' => $fechaTimbrado,
            ]);

            return $ventaBloqueada;
        }, 3);
    }

    protected function generarXmlCfdi40(Venta $venta, EmpresaConfiguracionFiscal $configFiscal): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $comprobante = $dom->createElement('cfdi:Comprobante');
        $comprobante->setAttribute('xmlns:cfdi', 'http://www.sat.gob.mx/cfd/4');
        $comprobante->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $comprobante->setAttribute('xsi:schemaLocation', 'http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd');
        $comprobante->setAttribute('Version', '4.0');
        $comprobante->setAttribute('Serie', 'POS');
        $comprobante->setAttribute('Folio', $venta->folio);
        $comprobante->setAttribute('Fecha', now()->format('Y-m-d\TH:i:s'));
        $comprobante->setAttribute('FormaPago', $venta->forma_pago_sat ?: '01');
        $comprobante->setAttribute('NoCertificado', '30001000000500003416');
        $comprobante->setAttribute('SubTotal', number_format((float) $venta->base_imponible, 2, '.', ''));
        $comprobante->setAttribute('Moneda', 'MXN');
        $comprobante->setAttribute('Total', number_format((float) $venta->total, 2, '.', ''));
        $comprobante->setAttribute('TipoDeComprobante', 'I');
        $comprobante->setAttribute('Exportacion', '01');
        $comprobante->setAttribute('MetodoPago', $venta->metodo_pago_sat ?: 'PUE');
        $comprobante->setAttribute('LugarExpedicion', $configFiscal->codigo_postal ?: '83000');
        $comprobante->setAttribute('Sello', 'SELLO_DIGITAL_CFDI_GENERADO');

        $dom->appendChild($comprobante);

        // Nodo Emisor
        $emisor = $dom->createElement('cfdi:Emisor');
        $emisor->setAttribute('Rfc', $configFiscal->rfc ?: 'XAXX010101000');
        $emisor->setAttribute('Nombre', $configFiscal->razon_social ?: 'CANO COMPUTADORAS');
        $emisor->setAttribute('RegimenFiscal', $configFiscal->regimen_fiscal ?: '601');
        $comprobante->appendChild($emisor);

        // Nodo Receptor
        $receptor = $dom->createElement('cfdi:Receptor');
        $receptor->setAttribute('Rfc', $venta->cliente_rfc ?: 'XAXX010101000');
        $receptor->setAttribute('Nombre', $venta->cliente_nombre ?: 'PUBLICO EN GENERAL');
        $receptor->setAttribute('DomicilioFiscalReceptor', $venta->cliente_codigo_postal ?: ($configFiscal->codigo_postal ?: '83000'));
        $receptor->setAttribute('RegimenFiscalReceptor', $venta->cliente_regimen_fiscal ?: '616');
        $receptor->setAttribute('UsoCFDI', $venta->uso_cfdi_sat ?: 'S01');
        $comprobante->appendChild($receptor);

        // Nodo Conceptos
        $conceptos = $dom->createElement('cfdi:Conceptos');
        foreach ($venta->detalles as $det) {
            /** @var VentaDetalle $det */
            $concepto = $dom->createElement('cfdi:Concepto');
            $concepto->setAttribute('ClaveProdServ', $det->clave_sat_producto ?: '43211500');
            $concepto->setAttribute('NoIdentificacion', $det->sku ?: (string) $det->producto_id);
            $concepto->setAttribute('Cantidad', number_format((float) $det->cantidad, 2, '.', ''));
            $concepto->setAttribute('ClaveUnidad', $det->clave_sat_unidad ?: 'H87');
            $concepto->setAttribute('Unidad', 'Pieza');
            $concepto->setAttribute('Descripcion', $det->descripcion_producto);
            $concepto->setAttribute('ValorUnitario', number_format((float) $det->precio_unitario, 2, '.', ''));
            $concepto->setAttribute('Importe', number_format((float) $det->subtotal, 2, '.', ''));
            $concepto->setAttribute('ObjetoImp', '02');

            // Impuestos del concepto
            $impuestosConcepto = $dom->createElement('cfdi:Impuestos');
            $trasladosConcepto = $dom->createElement('cfdi:Traslados');
            $trasladoConcepto = $dom->createElement('cfdi:Traslado');
            $trasladoConcepto->setAttribute('Base', number_format((float) $det->subtotal, 2, '.', ''));
            $trasladoConcepto->setAttribute('Impuesto', '002');
            $trasladoConcepto->setAttribute('TipoFactor', 'Tasa');
            $trasladoConcepto->setAttribute('TasaOCuota', '0.160000');
            $trasladoConcepto->setAttribute('Importe', number_format((float) bcmul((string) $det->monto_iva_unitario, (string) $det->cantidad, 2), 2, '.', ''));

            $trasladosConcepto->appendChild($trasladoConcepto);
            $impuestosConcepto->appendChild($trasladosConcepto);
            $concepto->appendChild($impuestosConcepto);

            $conceptos->appendChild($concepto);
        }
        $comprobante->appendChild($conceptos);

        // Nodo Impuestos Globales
        $impuestosGlobal = $dom->createElement('cfdi:Impuestos');
        $impuestosGlobal->setAttribute('TotalImpuestosTrasladados', number_format((float) $venta->iva_total, 2, '.', ''));

        $trasladosGlobal = $dom->createElement('cfdi:Traslados');
        $trasladoGlobal = $dom->createElement('cfdi:Traslado');
        $trasladoGlobal->setAttribute('Base', number_format((float) $venta->base_imponible, 2, '.', ''));
        $trasladoGlobal->setAttribute('Impuesto', '002');
        $trasladoGlobal->setAttribute('TipoFactor', 'Tasa');
        $trasladoGlobal->setAttribute('TasaOCuota', '0.160000');
        $trasladoGlobal->setAttribute('Importe', number_format((float) $venta->iva_total, 2, '.', ''));

        $trasladosGlobal->appendChild($trasladoGlobal);
        $impuestosGlobal->appendChild($trasladosGlobal);
        $comprobante->appendChild($impuestosGlobal);

        return (string) $dom->saveXML();
    }
}
