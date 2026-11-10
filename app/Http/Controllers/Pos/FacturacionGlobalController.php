<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Pos\FacturacionGlobalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View as ViewFacade;

class FacturacionGlobalController extends Controller
{
    public function __construct(
        protected FacturacionGlobalService $globalService
    ) {}

    /**
     * Muestra la vista de consulta de ventas candidatas y generación de factura global.
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

        abort_unless($sucursal !== null, 404, 'No hay sucursales activas.');

        $fechaInicioStr = $request->string('fecha_inicio')->toString();
        $fechaFinStr = $request->string('fecha_fin')->toString();

        $inicio = $fechaInicioStr !== '' ? Carbon::parse($fechaInicioStr)->startOfDay() : now()->startOfDay();
        $fin = $fechaFinStr !== '' ? Carbon::parse($fechaFinStr)->endOfDay() : now()->endOfDay();

        $ventas = $this->globalService->obtenerVentasCandidatas($empresa, $sucursal, $inicio, $fin);

        return ViewFacade::make('pos.facturacion.global', [
            'empresa' => $empresa,
            'sucursal' => $sucursal,
            'fechaInicio' => $inicio,
            'fechaFin' => $fin,
            'ventas' => $ventas,
            'totalVentas' => (float) $ventas->sum('total'),
        ]);
    }

    /**
     * Procesa y timbra la factura global consolidada ante el SAT.
     */
    public function procesar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Empresa $empresa */
        $empresa = $user->empresa ?? Empresa::query()->firstOrFail();

        /** @var Sucursal|null $sucursal */
        $sucursal = $user->sucursal
            ?? $user->sucursales()->where('sucursales.activo', true)->first()
            ?? Sucursal::query()->where('empresa_id', $empresa->id)->first();

        abort_unless($sucursal !== null, 404, 'No hay sucursales activas.');

        $validated = $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'periodicidad' => ['required', 'string', 'in:01,02,03,04,05'],
            'meses' => ['required', 'string', 'size:2'],
            'anio' => ['required', 'integer', 'min:2024', 'max:2030'],
            'forma_pago_predeterminada' => ['nullable', 'string', 'max:5'],
        ]);

        $inicio = Carbon::parse($validated['fecha_inicio'])->startOfDay();
        $fin = Carbon::parse($validated['fecha_fin'])->endOfDay();

        /** @var array{
         *     periodicidad: '01'|'02'|'03'|'04'|'05',
         *     meses: string,
         *     anio: int,
         *     forma_pago_predeterminada?: string
         * } $datosGlobal */
        $datosGlobal = [
            'periodicidad' => $validated['periodicidad'],
            'meses' => $validated['meses'],
            'anio' => (int) $validated['anio'],
            'forma_pago_predeterminada' => $validated['forma_pago_predeterminada'] ?? '01',
        ];

        $resultado = $this->globalService->generarFacturaGlobal(
            $empresa,
            $sucursal,
            $inicio,
            $fin,
            $datosGlobal,
            $user
        );

        return response()->json([
            'message' => 'Factura Global 4.0 timbrada exitosamente.',
            'uuid_sat' => $resultado['venta_global']->uuid_fiscal,
            'folio' => $resultado['venta_global']->serie_folio_factura,
            'ventas_consolidadas' => $resultado['ventas_consolidadas'],
            'total' => $resultado['total'],
        ]);
    }
}
