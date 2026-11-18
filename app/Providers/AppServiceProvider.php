<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Facturacion\Contracts\PacConnectorInterface;
use App\Services\Facturacion\MockPacConnector;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PacConnectorInterface::class,
            MockPacConnector::class
        );
    }

    public function boot(): void
    {
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
    }
}
