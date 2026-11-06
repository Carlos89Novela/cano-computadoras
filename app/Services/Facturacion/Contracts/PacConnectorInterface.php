<?php

namespace App\Services\Facturacion\Contracts;

use App\Models\EmpresaConfiguracionFiscal;

interface PacConnectorInterface
{
    /**
     * @param  string  $xmlSinTimbrar  XML sellado previamente listo para timbrar
     * @return array{
     *     exito: bool,
     *     uuid?: string,
     *     xml_timbrado?: string,
     *     fecha_timbrado?: string,
     *     sello_sat?: string,
     *     no_certificado_sat?: string,
     *     codigo_error?: string,
     *     mensaje_error?: string
     * }
     */
    public function timbrarXml(string $xmlSinTimbrar, EmpresaConfiguracionFiscal $configFiscal): array;

    /**
     * @return array{
     *     exito: bool,
     *     mensaje?: string,
     *     codigo_error?: string
     * }
     */
    public function cancelarCfdi(string $uuid, string $motivo, ?string $folioSustitucion, EmpresaConfiguracionFiscal $configFiscal): array;
}
