<?php

namespace App\Http\Controllers\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Restaurante\RestauranteReporteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View as ViewFacade;

class ReporteRestauranteController extends Controller
{
    public function __construct(
        protected RestauranteReporteService $reporteService
    ) {}

    /**
     * Muestra el panel interactivo de métricas y analítica del restaurante.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Empresa $empresa */
        $empresa = $user->empresa ?? Empresa::query()->firstOrFail();

        /** @var Sucursal|null $sucursal */
        $sucursal = $user->sucursal
            ?? $user->sucursales()->where('sucursales.activo', true)->first()
            ?? Sucursal::query()->where('empresa_id', $empresa->id)->first();

        abort_unless($sucursal !== null, 404, 'No hay sucursales activas registradas.');

        $fechaInicioStr = $request->string('fecha_inicio')->toString();
        $fechaFinStr = $request->string('fecha_fin')->toString();

        $fechaInicio = $fechaInicioStr !== '' ? Carbon::parse($fechaInicioStr)->startOfDay() : now()->startOfMonth();
        $fechaFin = $fechaFinStr !== '' ? Carbon::parse($fechaFinStr)->endOfDay() : now()->endOfDay();

        $metricas = $this->reporteService->obtenerMetricasPeriodo($empresa, $sucursal, $fechaInicio, $fechaFin);

        return ViewFacade::make('restaurante.reportes.index', [
            'empresa' => $empresa,
            'sucursal' => $sucursal,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'metricas' => $metricas,
        ]);
    }
}
