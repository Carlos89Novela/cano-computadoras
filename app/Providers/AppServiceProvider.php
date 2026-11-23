<?php

namespace App\Providers;

use App\Models\Empresa;
use App\Models\User;
use App\Observers\EmpresaObserver;
use App\Services\Facturacion\Contracts\PacConnectorInterface;
use App\Services\Facturacion\MockPacConnector;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Proveedor Principal de Servicios de la Aplicación.
 *
 * Responsabilidades:
 * - Registrar enlaces de contenedores de servicios (ej. conector PAC).
 * - Definir reglas globales de autorización (`Gate::before`) para cuentas propietarias.
 * - Registrar observers de modelos del dominio (ej. aprovisionamiento inicial de `Empresa`).
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra enlaces y contratos en el contenedor de servicios de Laravel.
     */
    public function register(): void
    {
        // Enlaza el contrato de facturación electrónica con el conector configurado
        $this->app->bind(
            PacConnectorInterface::class,
            MockPacConnector::class
        );
    }

    /**
     * Inicializa políticas globales de autorización y observadores de modelos.
     */
    public function boot(): void
    {
        // Concede autorización amplia a usuarios con estatus de propietario
        Gate::before(
            function (
                User $usuario,
                string $capacidad
            ): ?bool {
                if ($usuario->esPropietario()) {
                    return true;
                }

                return null;
            }
        );

        // Aprovisiona sucursal principal, gerente propietario, gerente operativo,
        // supervisor y personal del giro al crearse una nueva empresa
        Empresa::observe(EmpresaObserver::class);
    }
}