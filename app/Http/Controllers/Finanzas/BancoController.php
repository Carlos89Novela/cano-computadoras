<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finanzas\StoreMovimientoBancarioRequest;
use App\Models\CuentaBancaria;
use App\Models\Empresa;
use App\Models\MovimientoBancario;
use App\Models\User;
use App\Services\Finanzas\TesoreriaBancariaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View as ViewFacade;
use InvalidArgumentException;

class BancoController extends Controller
{
    public function __construct(
        protected TesoreriaBancariaService $tesoreriaService
    ) {}

    /**
     * Panel ejecutivo de cuentas bancarias y libro de movimientos.
     */
    public function index(Request $request, Empresa $empresa): View
    {
        $cuentas = CuentaBancaria::query()
            ->where('empresa_id', $empresa->id)
            ->withCount('movimientos')
            ->orderBy('nombre_cuenta')
            ->get();

        $cuentaSeleccionadaId = $request->query('cuenta_id');

        $queryMovimientos = MovimientoBancario::query()
            ->where('empresa_id', $empresa->id)
            ->with(['cuentaBancaria', 'cuentaDestino', 'usuario'])
            ->orderByDesc('fecha_operacion')
            ->orderByDesc('id');

        if ($cuentaSeleccionadaId !== null && is_numeric($cuentaSeleccionadaId)) {
            $queryMovimientos->where('cuenta_bancaria_id', (int) $cuentaSeleccionadaId);
        }

        $movimientos = $queryMovimientos->paginate(20)->withQueryString();

        $saldoTotal = (float) $cuentas->where('activa', true)->sum('saldo_actual');

        return ViewFacade::make('finanzas.bancos.index', [
            'empresa' => $empresa,
            'cuentas' => $cuentas,
            'movimientos' => $movimientos,
            'saldoTotal' => $saldoTotal,
            'cuentaSeleccionadaId' => $cuentaSeleccionadaId ? (int) $cuentaSeleccionadaId : null,
        ]);
    }

    /**
     * Registra un nuevo movimiento de tesorería (ingreso, egreso o transferencia).
     */
    public function storeMovimiento(StoreMovimientoBancarioRequest $request, Empresa $empresa): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $validated = $request->validated();
        $tipo = (string) $validated['tipo'];
        $monto = (float) $validated['monto'];
        $concepto = (string) $validated['concepto'];
        $fecha = (string) $validated['fecha_operacion'];
        $referencia = isset($validated['referencia']) ? (string) $validated['referencia'] : null;

        /** @var CuentaBancaria $cuenta */
        $cuenta = CuentaBancaria::query()
            ->where('empresa_id', $empresa->id)
            ->findOrFail((int) $validated['cuenta_bancaria_id']);

        try {
            if ($tipo === 'ingreso') {
                $this->tesoreriaService->registrarIngreso(
                    cuenta: $cuenta,
                    monto: $monto,
                    concepto: $concepto,
                    usuario: $usuario,
                    fecha: $fecha,
                    referencia: $referencia
                );
            } elseif ($tipo === 'egreso') {
                $this->tesoreriaService->registrarEgreso(
                    cuenta: $cuenta,
                    monto: $monto,
                    concepto: $concepto,
                    usuario: $usuario,
                    fecha: $fecha,
                    referencia: $referencia
                );
            } elseif ($tipo === 'transferencia') {
                /** @var CuentaBancaria $cuentaDestino */
                $cuentaDestino = CuentaBancaria::query()
                    ->where('empresa_id', $empresa->id)
                    ->findOrFail((int) $validated['cuenta_destino_id']);

                $this->tesoreriaService->transferirEntreCuentas(
                    origen: $cuenta,
                    destino: $cuentaDestino,
                    monto: $monto,
                    usuario: $usuario,
                    fecha: $fecha,
                    referencia: $referencia
                );
            }

            return redirect()->route('operacion.bancos.index', [
                'empresa' => $empresa->id,
                'cuenta_id' => $cuenta->id,
            ])->with('success', 'Movimiento bancario registrado exitosamente.');
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Concilia un movimiento contra el estado de cuenta bancario.
     */
    public function conciliar(Empresa $empresa, MovimientoBancario $movimiento): JsonResponse
    {
        if ($movimiento->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Movimiento no perteneciente a la empresa.'], 403);
        }

        $this->tesoreriaService->conciliarMovimiento($movimiento);

        return response()->json([
            'message' => 'Movimiento conciliado con éxito.',
            'conciliado' => true,
            'conciliado_at' => $movimiento->conciliado_at?->format('d/m/Y H:i'),
        ]);
    }
}
