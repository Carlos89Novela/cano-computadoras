<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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
