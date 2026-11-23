<?php

namespace App\Observers;

use App\Actions\Empresas\ProvisionarEstructuraEmpresa;
use App\Models\Empresa;

/**
 * Observer del modelo Empresa.
 *
 * Dispara el aprovisionamiento inicial de sucursal, gerente propietario,
 * gerente operativo, supervisor y personal del giro al crearse una empresa.
 */
class EmpresaObserver
{
    public function __construct(
        private ProvisionarEstructuraEmpresa $provisionarEstructura
    ) {}

    public function created(Empresa $empresa): void
    {
        $this->provisionarEstructura->ejecutar($empresa);
    }
}