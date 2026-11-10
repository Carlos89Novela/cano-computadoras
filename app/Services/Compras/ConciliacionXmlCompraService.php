<?php

namespace App\Services\Compras;

use App\Models\CuentaPorPagar;
use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ConciliacionXmlCompraService
{
    /**
     * Parsea un archivo XML de CFDI de proveedor (versiones 3.3 y 4.0).
     *
     * @return array{
     *     uuid_sat: string,
     *     serie: string|null,
     *     folio: string|null,
     *     fecha_emision: string,
     *     emisor_rfc: string,
     *     emisor_nombre: string,
     *     receptor_rfc: string,
     *     subtotal: float,
     *     iva_total: float,
     *     total: float
     * }
     */
    public function parsearXmlCfdi(string $xmlContenido): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContenido);

        if ($xml === false) {
            throw new InvalidArgumentException('El archivo no contiene un formato XML válido.');
        }

        $namespaces = $xml->getNamespaces(true);
        $cfdiNs = $namespaces['cfdi'] ?? 'http://www.sat.gob.mx/cfd/4';
        $tfdNs = $namespaces['tfd'] ?? 'http://www.sat.gob.mx/TimbreFiscalDigital';

        $atributosComprobante = $xml->attributes();
        $subtotal = (float) ((string) ($atributosComprobante['SubTotal'] ?? $atributosComprobante['subTotal'] ?? '0'));
        $total = (float) ((string) ($atributosComprobante['Total'] ?? $atributosComprobante['total'] ?? '0'));
        $serie = isset($atributosComprobante['Serie']) ? (string) $atributosComprobante['Serie'] : null;
        $folio = isset($atributosComprobante['Folio']) ? (string) $atributosComprobante['Folio'] : null;
        $fecha = (string) ($atributosComprobante['Fecha'] ?? $atributosComprobante['fecha'] ?? now()->toIso8601String());

        // Nodo Emisor
        $emisor = $xml->children($cfdiNs)->Emisor;
        $emisorAttr = $emisor ? $emisor->attributes() : null;
        $emisorRfc = (string) ($emisorAttr['Rfc'] ?? $emisorAttr['rfc'] ?? '');
        $emisorNombre = (string) ($emisorAttr['Nombre'] ?? $emisorAttr['nombre'] ?? '');

        // Nodo Receptor
        $receptor = $xml->children($cfdiNs)->Receptor;
        $receptorAttr = $receptor ? $receptor->attributes() : null;
        $receptorRfc = (string) ($receptorAttr['Rfc'] ?? $receptorAttr['rfc'] ?? '');

        // Nodo Complemento -> TimbreFiscalDigital (UUID)
        $complemento = $xml->children($cfdiNs)->Complemento;
        $uuid = '';
        if ($complemento) {
            $tfd = $complemento->children($tfdNs)->TimbreFiscalDigital;
            if ($tfd) {
                $tfdAttr = $tfd->attributes();
                $uuid = (string) ($tfdAttr['UUID'] ?? $tfdAttr['uuid'] ?? '');
            }
        }

        if ($uuid === '') {
            throw new InvalidArgumentException('El CFDI no contiene un Timbre Fiscal Digital (UUID) válido.');
        }

        // Impuestos
        $impuestos = $xml->children($cfdiNs)->Impuestos;
        $ivaTotal = 0.0;
        if ($impuestos) {
            $impAttr = $impuestos->attributes();
            $ivaTotal = (float) ((string) ($impAttr['TotalImpuestosTrasladados'] ?? '0'));
        }

        if ($ivaTotal <= 0.0 && $total > $subtotal) {
            $ivaTotal = round($total - $subtotal, 4);
        }

        return [
            'uuid_sat' => strtoupper($uuid),
            'serie' => $serie,
            'folio' => $folio,
            'fecha_emision' => $fecha,
            'emisor_rfc' => strtoupper($emisorRfc),
            'emisor_nombre' => $emisorNombre,
            'receptor_rfc' => strtoupper($receptorRfc),
            'subtotal' => $subtotal,
            'iva_total' => $ivaTotal,
            'total' => $total,
        ];
    }

    /**
     * Registra una Cuenta por Pagar a partir de un archivo XML cargado.
     *
     * @param array{
     *     proveedor_id?: int|null,
     *     recepcion_inventario_id?: int|null,
     *     orden_compra_id?: int|null,
     *     dias_credito?: int|null,
     *     notas?: string|null
     * } $datosAdicionales
     */
    public function registrarDesdeXml(
        UploadedFile $archivoXml,
        Empresa $empresa,
        Sucursal $sucursal,
        User $usuario,
        array $datosAdicionales = []
    ): CuentaPorPagar {
        $contenido = (string) file_get_contents($archivoXml->getRealPath());
        $datosCfdi = $this->parsearXmlCfdi($contenido);

        // Verificar que no esté registrada previamente
        $existe = CuentaPorPagar::query()
            ->where('empresa_id', $empresa->id)
            ->where('uuid_sat', $datosCfdi['uuid_sat'])
            ->exists();

        if ($existe) {
            throw new InvalidArgumentException("La factura con UUID {$datosCfdi['uuid_sat']} ya se encuentra registrada en Cuentas por Pagar.");
        }

        // Buscar o registrar proveedor si no viene explícito
        $proveedorId = $datosAdicionales['proveedor_id'] ?? null;
        if (! $proveedorId) {
            $proveedor = Proveedor::query()
                ->where('empresa_id', $empresa->id)
                ->where('rfc', $datosCfdi['emisor_rfc'])
                ->first();

            if (! $proveedor) {
                $totalProveedores = Proveedor::query()->where('empresa_id', $empresa->id)->count();
                $codigoProveedor = 'PRV-'.str_pad((string) ($totalProveedores + 1), 4, '0', STR_PAD_LEFT);
                $nombreProveedor = $datosCfdi['emisor_nombre'] ?: 'PROVEEDOR '.$datosCfdi['emisor_rfc'];

                /** @var Proveedor $proveedor */
                $proveedor = Proveedor::query()->create([
                    'empresa_id' => $empresa->id,
                    'codigo' => $codigoProveedor,
                    'nombre' => $nombreProveedor,
                    'razon_social' => $nombreProveedor,
                    'rfc' => $datosCfdi['emisor_rfc'],
                    'dias_credito' => $datosAdicionales['dias_credito'] ?? 30,
                    'activo' => true,
                    'creado_por_id' => $usuario->id,
                ]);
            }
            $proveedorId = $proveedor->id;
        }

        $diasCredito = (int) ($datosAdicionales['dias_credito'] ?? 30);
        $fechaEmision = Carbon::parse($datosCfdi['fecha_emision']);
        $fechaVencimiento = (clone $fechaEmision)->addDays($diasCredito)->startOfDay();

        // Almacenar el archivo XML físicamente
        $rutaXml = $archivoXml->storeAs(
            "cxp/{$empresa->id}",
            "{$datosCfdi['uuid_sat']}.xml",
            'local'
        );

        /** @var CuentaPorPagar $cxp */
        $cxp = CuentaPorPagar::query()->create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'proveedor_id' => $proveedorId,
            'recepcion_inventario_id' => $datosAdicionales['recepcion_inventario_id'] ?? null,
            'orden_compra_id' => $datosAdicionales['orden_compra_id'] ?? null,
            'uuid_sat' => $datosCfdi['uuid_sat'],
            'serie' => $datosCfdi['serie'],
            'folio' => $datosCfdi['folio'],
            'xml_path' => $rutaXml,
            'fecha_emision' => $fechaEmision,
            'fecha_vencimiento' => $fechaVencimiento,
            'subtotal' => $datosCfdi['subtotal'],
            'iva_total' => $datosCfdi['iva_total'],
            'total' => $datosCfdi['total'],
            'monto_pagado' => 0.0,
            'saldo_pendiente' => $datosCfdi['total'],
            'estado' => 'pendiente',
            'notas' => $datosAdicionales['notas'] ?? null,
            'registrado_por_user_id' => $usuario->id,
        ]);

        return $cxp;
    }
}
