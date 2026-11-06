<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrdenCompra\StoreOrdenCompraRequest;
use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Services\Compras\CrearOrdenCompraService;
use App\Services\Compras\TransicionEstadoOrdenCompraService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFacade;
use Symfony\Component\HttpFoundation\Response;

class OrdenCompraController extends Controller
{
    protected function obtenerEmpresa(Request $request): Empresa
    {
        $user = $request->user();

        /** @var Empresa|null $empresa */
        $empresa = $user->empresa;

        if (! $empresa && ! empty($user->empresa_id)) {
            $empresa = Empresa::query()->find($user->empresa_id);
        }

        if (! $empresa) {
            /** @var Empresa $empresa */
            $empresa = Empresa::query()->firstOrFail();
        }

        return $empresa;
    }

    public function index(Request $request): View
    {
        $empresa = $this->obtenerEmpresa($request);

        $ordenes = OrdenCompra::query()
            ->where('empresa_id', $empresa->id)
            ->with(['proveedor:id,razon_social,nombre,rfc', 'sucursal:id,nombre', 'usuario:id,name'])
            ->latest('id')
            ->paginate(15);

        return ViewFacade::make('compras.ordenes.index', compact('ordenes', 'empresa'));
    }

    public function create(Request $request): View
    {
        $empresa = $this->obtenerEmpresa($request);

        $sucursales = Sucursal::query()->where('empresa_id', $empresa->id)->get(['id', 'nombre', 'codigo']);
        $almacenes = Almacen::query()->where('empresa_id', $empresa->id)->where('activo', true)->get(['id', 'nombre', 'sucursal_id']);
        $proveedores = Proveedor::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->orderBy('razon_social')
            ->get(['id', 'razon_social', 'nombre', 'rfc']);
        $productos = Producto::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'sku', 'nombre', 'costo_referencia']);

        return ViewFacade::make('compras.ordenes.create', compact('empresa', 'sucursales', 'almacenes', 'proveedores', 'productos'));
    }

    public function store(StoreOrdenCompraRequest $request, CrearOrdenCompraService $service): RedirectResponse
    {
        $user = $request->user();
        $empresa = $this->obtenerEmpresa($request);

        $datos = $request->validated();
        $datos['empresa_id'] = $empresa->id;
        $datos['user_id'] = $user->id;

        $orden = $service->ejecutar($datos);

        return redirect()
            ->route('compras.ordenes.show', $orden->id)
            ->with('status', "Orden de compra {$orden->folio} registrada exitosamente.");
    }

    public function show(OrdenCompra $orden): View
    {
        $orden->load(['detalles.producto', 'proveedor', 'sucursal', 'almacen', 'usuario', 'autorizador']);

        return ViewFacade::make('compras.ordenes.show', compact('orden'));
    }

    public function solicitar(OrdenCompra $orden, TransicionEstadoOrdenCompraService $service): RedirectResponse
    {
        try {
            $service->solicitar($orden);

            return back()->with('status', "La orden {$orden->folio} ha sido enviada para autorización.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function autorizar(OrdenCompra $orden, TransicionEstadoOrdenCompraService $service, Request $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $service->autorizar($orden, $user);

            return back()->with('status', "La orden {$orden->folio} ha sido autorizada formalmente.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function cancelar(Request $request, OrdenCompra $orden, TransicionEstadoOrdenCompraService $service): RedirectResponse
    {
        $request->validate([
            'motivo_cancelacion' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $service->cancelar($orden, (string) $request->input('motivo_cancelacion'));

            return back()->with('status', "La orden {$orden->folio} ha sido cancelada.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function pdf(OrdenCompra $orden): Response
    {
        $orden->load([
            'detalles.producto',
            'proveedor',
            'sucursal',
            'almacen',
            'usuario',
            'autorizador',
            'empresa',
        ]);

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('compras.ordenes.pdf', compact('orden'))
            ->setPaper('letter', 'portrait');

        return $pdf->stream("Orden-Compra-{$orden->folio}.pdf");
    }
}
