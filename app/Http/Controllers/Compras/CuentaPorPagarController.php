<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\CuentaPorPagar;
use App\Models\CuentaPorPagarPago;
use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Compras\ConciliacionXmlCompraService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View as ViewFacade;

class CuentaPorPagarController extends Controller
{
    public function __construct(
        protected ConciliacionXmlCompraService $conciliacionService
    ) {}

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

        $estadoFiltro = $request->string('estado')->toString();
        $proveedorId = $request->integer('proveedor_id');

        $query = CuentaPorPagar::query()
            ->where('empresa_id', $empresa->id)
            ->where('sucursal_id', $sucursal->id)
            ->with(['proveedor', 'registradoPor']);

        if ($estadoFiltro !== '') {
            $query->where('estado', $estadoFiltro);
        }

        if ($proveedorId > 0) {
            $query->where('proveedor_id', $proveedorId);
        }

        $cuentas = $query->orderBy('fecha_vencimiento')->get();
        $proveedores = Proveedor::query()->where('empresa_id', $empresa->id)->orderBy('razon_social')->get();

        $saldoTotalPendiente = (float) $cuentas->whereIn('estado', ['pendiente', 'parcial'])->sum('saldo_pendiente');
        $totalVencido = (float) $cuentas->whereIn('estado', ['pendiente', 'parcial'])
            ->filter(fn (CuentaPorPagar $c) => $c->fecha_vencimiento->isPast())
            ->sum('saldo_pendiente');

        return ViewFacade::make('compras.cuentas_por_pagar.index', [
            'empresa' => $empresa,
            'sucursal' => $sucursal,
            'cuentas' => $cuentas,
            'proveedores' => $proveedores,
            'saldoTotalPendiente' => $saldoTotalPendiente,
            'totalVencido' => $totalVencido,
            'estadoFiltro' => $estadoFiltro,
            'proveedorId' => $proveedorId,
        ]);
    }

    public function subirXml(Request $request): JsonResponse
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

        $request->validate([
            'xml_file' => ['required', 'file', 'mimes:xml'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'dias_credito' => ['nullable', 'integer', 'min:0', 'max:180'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var UploadedFile $archivoXml */
        $archivoXml = $request->file('xml_file');

        $cxp = $this->conciliacionService->registrarDesdeXml(
            $archivoXml,
            $empresa,
            $sucursal,
            $user,
            [
                'proveedor_id' => $request->integer('proveedor_id') ?: null,
                'dias_credito' => $request->integer('dias_credito') ?: 30,
                'notas' => $request->string('notas')->toString() ?: null,
            ]
        );

        return response()->json([
            'message' => 'Factura de compra registrada y conciliada exitosamente.',
            'cuenta_id' => $cxp->id,
            'uuid_sat' => $cxp->uuid_sat,
            'proveedor' => $cxp->proveedor->razon_social,
            'total' => $cxp->total,
        ]);
    }

    public function registrarPago(Request $request, CuentaPorPagar $cuenta): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01', "max:{$cuenta->saldo_pendiente}"],
            'fecha_pago' => ['required', 'date'],
            'forma_pago_sat' => ['required', 'string', 'max:5'],
            'referencia_bancaria' => ['nullable', 'string', 'max:100'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        $montoAbono = round((float) $validated['monto'], 4);

        DB::transaction(function () use ($cuenta, $user, $validated, $montoAbono) {
            CuentaPorPagarPago::query()->create([
                'cuenta_por_pagar_id' => $cuenta->id,
                'user_id' => $user->id,
                'fecha_pago' => Carbon::parse($validated['fecha_pago']),
                'monto' => $montoAbono,
                'forma_pago_sat' => $validated['forma_pago_sat'],
                'referencia_bancaria' => $validated['referencia_bancaria'] ?? null,
                'notas' => $validated['notas'] ?? null,
            ]);

            $nuevoMontoPagado = round((float) $cuenta->monto_pagado + $montoAbono, 4);
            $nuevoSaldo = max(0.0, round((float) $cuenta->total - $nuevoMontoPagado, 4));
            $nuevoEstado = $nuevoSaldo <= 0.0001 ? 'pagada' : 'parcial';

            $cuenta->update([
                'monto_pagado' => $nuevoMontoPagado,
                'saldo_pendiente' => $nuevoSaldo,
                'estado' => $nuevoEstado,
            ]);
        });

        return response()->json([
            'message' => 'Pago aplicado exitosamente.',
            'saldo_pendiente' => $cuenta->fresh()->saldo_pendiente,
            'estado' => $cuenta->fresh()->estado,
        ]);
    }
}
