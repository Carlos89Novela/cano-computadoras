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

/**
 * Controlador de Cuentas por Pagar (CXP) a proveedores.
 *
 * Resuelve la empresa y sucursal activas mediante sesión o la relación
 * `sucursal_usuario` (sin depender de columnas directas en `users`),
 * conciliación de CFDI/XML y aplicación de pagos con aislamiento multi-tenant.
 */
class CuentaPorPagarController extends Controller
{
    public function __construct(
        protected ConciliacionXmlCompraService $conciliacionService
    ) {}

    /**
     * Lista las cuentas por pagar de la empresa y sucursal activas con filtros
     * por estado y proveedor.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        [$empresa, $sucursal] = $this->resolverContextoActivo($request, $user);

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
        $proveedores = Proveedor::query()
            ->where('empresa_id', $empresa->id)
            ->orderBy('razon_social')
            ->get();

        $saldoTotalPendiente = (float) $cuentas
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->sum('saldo_pendiente');

        $totalVencido = (float) $cuentas
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->filter(fn (CuentaPorPagar $c) => $c->fecha_vencimiento && $c->fecha_vencimiento->isPast())
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

    /**
     * Registra y concilia una factura de compra desde archivo XML dentro de
     * la empresa y sucursal activas.
     */
    public function subirXml(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        [$empresa, $sucursal] = $this->resolverContextoActivo($request, $user);

        $request->validate([
            'xml_file' => ['required', 'file', 'mimes:xml'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'dias_credito' => ['nullable', 'integer', 'min:0', 'max:180'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        // Si se indica proveedor_id, validar que pertenezca a la empresa activa
        $proveedorId = $request->integer('proveedor_id');
        if ($proveedorId > 0) {
            Proveedor::query()
                ->where('empresa_id', $empresa->id)
                ->findOrFail($proveedorId);
        }

        /** @var UploadedFile $archivoXml */
        $archivoXml = $request->file('xml_file');

        $cxp = $this->conciliacionService->registrarDesdeXml(
            $archivoXml,
            $empresa,
            $sucursal,
            $user,
            [
                'proveedor_id' => $proveedorId > 0 ? $proveedorId : null,
                'dias_credito' => $request->integer('dias_credito') ?: 30,
                'notas' => $request->string('notas')->toString() ?: null,
            ]
        );

        return response()->json([
            'message' => 'Factura de compra registrada y conciliada exitosamente.',
            'cuenta_id' => $cxp->id,
            'uuid_sat' => $cxp->uuid_sat,
            'proveedor' => $cxp->proveedor?->razon_social ?? $cxp->proveedor?->nombre,
            'total' => $cxp->total,
        ]);
    }

    /**
     * Registra un abono o liquidación a una cuenta por pagar verificando su
     * pertenencia a la empresa activa del usuario.
     */
    public function registrarPago(Request $request, CuentaPorPagar $cuenta): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        [$empresa] = $this->resolverContextoActivo($request, $user);

        abort_unless((int) $cuenta->empresa_id === (int) $empresa->id, 403, 'La cuenta por pagar no pertenece a la empresa activa.');

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

    /**
     * Resuelve la empresa y sucursal activas mediante sesión y pivote `sucursal_usuario`.
     *
     * @return array{0: Empresa, 1: Sucursal}
     */
    protected function resolverContextoActivo(Request $request, User $user): array
    {
        /** @var Empresa|null $empresa */
        $empresa = null;

        $sessionEmpresaId = $request->session()->get('empresa_activa_id')
            ?? $request->session()->get('empresa_id');

        if (! empty($sessionEmpresaId)) {
            $empresa = Empresa::query()->find((int) $sessionEmpresaId);
        }

        $sucursalActiva = null;
        if (method_exists($user, 'sucursales')) {
            $querySucursales = $user->sucursales()
                ->wherePivot('activo', true)
                ->orderByDesc('sucursal_usuario.es_principal')
                ->with('empresa');

            if ($empresa) {
                $sucursalActiva = (clone $querySucursales)
                    ->where('sucursales.empresa_id', $empresa->id)
                    ->first();
            }

            if (! $sucursalActiva) {
                $sucursalActiva = $querySucursales->first();
                if ($sucursalActiva?->empresa instanceof Empresa) {
                    $empresa = $sucursalActiva->empresa;
                }
            }
        }

        if (! $empresa) {
            $empresa = Empresa::query()->where('activo', true)->firstOrFail();
        }

        $request->session()->put('empresa_activa_id', $empresa->id);

        /** @var Sucursal|null $sucursal */
        $sucursal = $sucursalActiva
            ?? Sucursal::query()->where('empresa_id', $empresa->id)->where('activo', true)->first()
            ?? Sucursal::query()->where('empresa_id', $empresa->id)->first();

        abort_unless($sucursal !== null, 404, 'No hay sucursales activas para la empresa.');

        return [$empresa, $sucursal];
    }
}