<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\CajaTurno;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Pos\RegistrarMovimientoCajaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFacade;
use Symfony\Component\HttpFoundation\Response;

class CajaAuditoriaController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Empresa|null $empresa */
        $empresa = $user->empresa ?? Empresa::query()->where('activo', true)->first();

        if (! $empresa) {
            /** @var Empresa $empresa */
            $empresa = Empresa::query()->firstOrFail();
        }

        $sucursales = Sucursal::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->get(['id', 'nombre', 'codigo']);

        $sucursalId = $request->integer('sucursal_id');
        $estado = $request->string('estado')->toString();

        $turnos = CajaTurno::query()
            ->where('empresa_id', $empresa->id)
            ->when($sucursalId > 0, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->when($estado !== '', fn ($q) => $q->where('estado', $estado))
            ->with(['sucursal:id,nombre', 'user:id,name'])
            ->latest('id')
            ->paginate(15);

        return ViewFacade::make('pos.cajas.index', compact('turnos', 'sucursales', 'empresa'));
    }

    public function show(CajaTurno $turno): View
    {
        $turno->load([
            'sucursal',
            'user',
            'ventas' => fn ($q) => $q->latest('id')->take(20),
            'pagos',
            'movimientos.usuario',
            'movimientos.autorizador',
        ]);

        return ViewFacade::make('pos.cajas.show', compact('turno'));
    }

    public function registrarMovimiento(
        Request $request,
        CajaTurno $turno,
        RegistrarMovimientoCajaService $service
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();

        /** @var array{
         *     tipo: 'ingreso'|'retiro',
         *     concepto: string,
         *     monto: float|int|numeric-string,
         *     motivo: string,
         *     comprobante_numero?: string|null
         * } $validated */
        $validated = $request->validate([
            'tipo' => ['required', 'in:ingreso,retiro'],
            'concepto' => ['required', 'string', 'max:100'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
            'comprobante_numero' => ['nullable', 'string', 'max:50'],
        ]);

        $datos = array_merge($validated, ['caja_turno_id' => $turno->id]);
        $service->ejecutar($datos, $actor);

        return back()->with('status', 'Movimiento de caja registrado exitosamente.');
    }

    public function cortePdf(CajaTurno $turno): Response
    {
        $turno->load(['sucursal', 'user', 'empresa', 'pagos', 'movimientos.usuario']);

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('pos.cajas.corte_pdf', compact('turno'))
            ->setPaper([0, 0, 226.77, 600], 'portrait'); // Formato ticket 80mm

        return $pdf->stream("Corte-Caja-{$turno->id}.pdf");
    }
}
