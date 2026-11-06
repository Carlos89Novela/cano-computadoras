<?php

namespace App\Services\Facturacion;

use App\Models\EmpresaConfiguracionFiscal;
use App\Services\Facturacion\Contracts\PacConnectorInterface;
use Illuminate\Support\Str;

class MockPacConnector implements PacConnectorInterface
{
    public function timbrarXml(string $xmlSinTimbrar, EmpresaConfiguracionFiscal $configFiscal): array
    {
        $uuid = (string) Str::uuid();
        $fechaTimbrado = now()->format('Y-m-d\TH:i:s');
        $selloSat = Str::random(128);
        $noCertificadoSat = '30001000000500003416';

        // Simulación de nodo TimbreFiscalDigital
        $complementoTfd = sprintf(
            '<tfd:TimbreFiscalDigital xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="1.1" UUID="%s" FechaTimbrado="%s" RfcProvCertif="MOCK010101000" SelloCFD="SELLO_CFD_MOCK" NoCertificadoSAT="%s" SelloSAT="%s" />',
            $uuid,
            $fechaTimbrado,
            $noCertificadoSat,
            $selloSat
        );

        $xmlTimbrado = str_replace('</cfdi:Comprobante>', $complementoTfd.'</cfdi:Comprobante>', $xmlSinTimbrar);

        return [
            'exito' => true,
            'uuid' => $uuid,
            'xml_timbrado' => $xmlTimbrado,
            'fecha_timbrado' => $fechaTimbrado,
            'sello_sat' => $selloSat,
            'no_certificado_sat' => $noCertificadoSat,
        ];
    }

    public function cancelarCfdi(string $uuid, string $motivo, ?string $folioSustitucion, EmpresaConfiguracionFiscal $configFiscal): array
    {
        return [
            'exito' => true,
            'mensaje' => "Comprobante {$uuid} cancelado exitosamente en modo simulación (Motivo: {$motivo}).",
        ];
    }
}
