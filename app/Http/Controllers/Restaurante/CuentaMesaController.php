<?php

namespace App\Http\Controllers\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\CajaTurno;
use App\Models\Comanda;
use App\Models\RestauranteMesa;
use App\Models\User;
use App\Models\Venta;
use App\Services\Pos\RegistrarVentaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\Str;

class CuentaMesaController extends Controller
{
    public function __construct(
        protected RegistrarVentaService $ventaService
    ) {}

    /**
     * Genera la vista de impresión térmica de la precuenta para entregar al cliente.
     */
    public function precuenta(RestauranteMesa $mesa): View
    {
        $comanda = $mesa->comandaActual;
        abort_unless($comanda !== null, 404, 'La mesa no tiene una comanda activa.');

        $comanda->load(['items.producto', 'mesero', 'empresa', 'sucursal']);

        // Calcular subtotal, IVA sugerido y total de consumo
        $totalConsumo = 0.0;
        foreach ($comanda->items as $item) {
            $totalConsumo += ((float) $item->cantidad) * ((float) $item->producto->precio_base);
        }

        // Generar o recuperar token temporal de autofacturación
        $tokenFactura = Str::upper(Str::random(12));

        return ViewFacade::make('restaurante.mesas.precuenta_ticket', [
            'mesa' => $mesa,
            'comanda' => $comanda,
            'totalConsumo' => $totalConsumo,
            'tokenFactura' => $tokenFactura,
        ]);
    }

    /**
     * Solicita la cuenta (marca la mesa en color azul en el mapa visual de piso).
     */
    public function pedirCuenta(RestauranteMesa $mesa): JsonResponse
    {
        $mesa->update(['estado' => 'cuenta_pedida']);

        return response()->json(['message' => 'Cuenta solicitada. Mesa marcada en el plano.']);
    }

    /**
     * Cobra y liquida la mesa, generando la venta en el POS, descontando Kardex y liberando la mesa.
     */
    public function liquidarMesa(Request $request, RestauranteMesa $mesa): JsonResponse
    {
        /** @var User $cajero */
        $cajero = $request->user();

        $comanda = $mesa->comandaActual;
        abort_unless($comanda !== null, 422, 'La mesa no cuenta con orden abierta.');

        $comanda->load(['items.producto']);

        $validated = $request->validate([
            'forma_pago_sat' => ['required', 'string', 'max:5'],
            'monto_recibido' => ['nullable', 'numeric', 'min:0'],
            'propina' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lineasVenta = [];
        $totalVenta = 0.0;

        foreach ($comanda->items as $item) {
            $precio = (float) $item->producto->precio_base;
            $lineasVenta[] = [
                'producto_id' => $item->producto_id,
                'cantidad' => (float) $item->cantidad,
                'precio_unitario' => $precio,
                'tasa_iva' => 0.1600,
            ];
            $totalVenta += ((float) $item->cantidad) * $precio;
        }

        $turno = CajaTurno::query()
            ->where('sucursal_id', $mesa->area->sucursal_id)
            ->where('estado', 'abierto')
            ->latest('id')
            ->first();

        /** @var Almacen|null $almacen */
        $almacen = Almacen::query()
            ->where('sucursal_id', $mesa->area->sucursal_id)
            ->where('activo', true)
            ->first();

        $almacenId = $almacen !== null ? (int) $almacen->id : 1;

        // Generar token único para el portal de autofacturación pública (vigente 72 horas)
        $tokenAutofactura = Str::lower(Str::random(32));

        $venta = DB::transaction(function () use ($cajero, $mesa, $comanda, $lineasVenta, $totalVenta, $validated, $turno, $almacenId, $tokenAutofactura) {
            $ventaRegistrada = $this->ventaService->ejecutar([
                'empresa_id' => $mesa->area->empresa_id,
                'sucursal_id' => $mesa->area->sucursal_id,
                'almacen_id' => $almacenId,
                'caja_turno_id' => $turno?->id,
                'user_id' => $cajero->id,
                'cliente_nombre' => $mesa->numero_o_nombre,
                'cliente_rfc' => 'XAXX010101000',
                'forma_pago_sat' => $validated['forma_pago_sat'],
                'lineas' => $lineasVenta,
                'pagos' => [
                    [
                        'forma_pago_sat' => $validated['forma_pago_sat'],
                        'monto' => $totalVenta,
                        'monto_recibido' => $validated['monto_recibido'] ?? $totalVenta,
                        'cambio_devuelto' => max(0.0, ((float) ($validated['monto_recibido'] ?? $totalVenta)) - $totalVenta),
                    ],
                ],
            ]);

            // Asignar el token de autofacturación
            $ventaRegistrada->update([
                'token_autofactura' => $tokenAutofactura,
                'autofactura_limite_at' => now()->addDays(3),
            ]);

            // Completar comanda y desvincular mesa
            $comanda->update([
                'estado' => 'completada',
                'venta_id' => $ventaRegistrada->id,
                'finalizada_at' => now(),
            ]);

            // Liberar la mesa en el mapa visual
            $mesa->update([
                'estado' => 'disponible',
                'comanda_actual_id' => null,
            ]);

            return $ventaRegistrada;
        });

        return response()->json([
            'message' => "Mesa {$mesa->numero_o_nombre} cobrada y liberada con éxito.",
            'venta_folio' => $venta->folio,
            'venta_uuid' => $venta->uuid,
            'token_autofactura' => $tokenAutofactura,
        ]);
    }
}
