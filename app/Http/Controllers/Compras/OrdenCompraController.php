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

/**
 * Controlador de Órdenes de Compra a Proveedores (Multi-Tenant y Multi-Giro).
 */
class OrdenCompraController extends Controller
{
    /**
     * Resuelve la empresa activa del usuario autenticado priorizando:
     * 1) Parámetro explícito en ruta o query (?empresa=3)
     * 2) Sesión activa (empresa_activa_id / empresa_id)
     * 3) Empresa o sucursal asignada al usuario en base de datos
     * 4) Primera empresa activa como respaldo final
     */
    /**
     * Resuelve la empresa activa del usuario autenticado usando ruta,
     * sesión multi-tenant o la sucursal activa asignada en sucursal_usuario.
     */
    protected function obtenerEmpresa(Request $request): Empresa
    {
        $user = $request->user();

        // 1) Parámetro explícito en ruta o query (?empresa=3)
        $empresaParam = $request->route('empresa') ?? $request->query('empresa');
        if ($empresaParam instanceof Empresa) {
            session(['empresa_activa_id' => $empresaParam->id]);
            return $empresaParam;
        }
        if (is_numeric($empresaParam)) {
            $encontrada = Empresa::query()->find((int) $empresaParam);
            if ($encontrada) {
                session(['empresa_activa_id' => $encontrada->id]);
                return $encontrada;
            }
        }

        // 2) Empresa guardada en sesión activa
        $sessionEmpresaId = session('empresa_activa_id') ?? session('empresa_id');
        if (! empty($sessionEmpresaId)) {
            $encontrada = Empresa::query()->find((int) $sessionEmpresaId);
            if ($encontrada) {
                return $encontrada;
            }
        }

        // 3) Empresa de la sucursal principal/activa asignada al usuario en sucursal_usuario
        /** @var Empresa|null $empresa */
        $empresa = null;

        if ($user && method_exists($user, 'sucursales')) {
            $sucursalActiva = $user->sucursales()
                ->wherePivot('activo', true)
                ->orderByDesc('sucursal_usuario.es_principal')
                ->with('empresa')
                ->first();

            if ($sucursalActiva?->empresa instanceof Empresa) {
                $empresa = $sucursalActiva->empresa;
                session(['empresa_activa_id' => $empresa->id]);
            }
        }

        // 4) Respaldo final a la primera empresa activa registrada
        if (! $empresa) {
            /** @var Empresa $empresa */
            $empresa = Empresa::query()->where('activo', true)->firstOrFail();
        }

        return $empresa;
    }

    /**
     * Valida que la orden de compra pertenezca a la empresa activa del usuario.
     */
    protected function autorizarOrdenDeEmpresa(Request $request, OrdenCompra $orden): Empresa
    {
        $empresa = $this->obtenerEmpresa($request);

        abort_unless(
            (int) $orden->empresa_id === (int) $empresa->id,
            403,
            'La orden de compra no pertenece a la empresa activa.'
        );

        return $empresa;
    }

    public function index(Request $request): View
    {
        $empresa = $this->obtenerEmpresa($request);

        $ordenes = OrdenCompra::query()
            ->where('empresa_id', $empresa->id)
            ->with([
                'proveedor:id,razon_social,nombre,rfc',
                'sucursal:id,nombre',
                'almacen:id,nombre',
                'usuario:id,name',
            ])
            ->latest('id')
            ->paginate(15);

        return ViewFacade::make('compras.ordenes.index', compact('ordenes', 'empresa'));
    }

    public function create(Request $request): View
    {
        $empresa = $this->obtenerEmpresa($request);
        $giro = strtolower((string) ($empresa->giro_comercial ?? ''));
        $esRestaurante = in_array($giro, ['restaurantes_alimentos', 'restaurante'], true);

        $sucursales = Sucursal::query()
            ->where('empresa_id', $empresa->id)
            ->get(['id', 'nombre', 'codigo']);

        $almacenes = Almacen::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->get(['id', 'nombre', 'sucursal_id']);

        $proveedores = Proveedor::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->orderBy('razon_social')
            ->get(['id', 'razon_social', 'nombre', 'rfc']);

        $queryProductos = Producto::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->orderBy('nombre');

        // En giro restaurante filtra únicamente insumos (SKU "INS-")
        if ($esRestaurante) {
            $queryProductos->where(function ($q) {
                $q->where('sku', 'like', 'INS-%')
                  ->orWhere('sku', 'like', 'ins-%');
            });
        }

        $productos = $queryProductos->get(['id', 'sku', 'nombre', 'costo_referencia']);

        return ViewFacade::make(
            'compras.ordenes.create',
            compact('empresa', 'sucursales', 'almacenes', 'proveedores', 'productos')
        );
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

    public function show(Request $request, OrdenCompra $orden): View
    {
        $this->autorizarOrdenDeEmpresa($request, $orden);

        $orden->load(['detalles.producto', 'proveedor', 'sucursal', 'almacen', 'usuario', 'autorizador']);

        return ViewFacade::make('compras.ordenes.show', compact('orden'));
    }

    public function solicitar(Request $request, OrdenCompra $orden, TransicionEstadoOrdenCompraService $service): RedirectResponse
    {
        $this->autorizarOrdenDeEmpresa($request, $orden);

        try {
            $service->solicitar($orden);

            return back()->with('status', "La orden {$orden->folio} ha sido enviada para autorización.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function autorizar(Request $request, OrdenCompra $orden, TransicionEstadoOrdenCompraService $service): RedirectResponse
    {
        $this->autorizarOrdenDeEmpresa($request, $orden);
        $user = $request->user();

        $esGerente = $user && (
            (method_exists($user, 'esPropietario') && $user->esPropietario())
            || ($user->hasRole('administrador') && ! $user->hasRole('supervisor'))
        );

        abort_unless($esGerente, 403, 'Solo el Gerente (administrador) puede autorizar órdenes de compra.');

        try {
            $service->autorizar($orden, $user);

            return back()->with('status', "La orden {$orden->folio} ha sido autorizada formalmente.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function cancelar(Request $request, OrdenCompra $orden, TransicionEstadoOrdenCompraService $service): RedirectResponse
    {
        $this->autorizarOrdenDeEmpresa($request, $orden);

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

    public function pdf(Request $request, OrdenCompra $orden): Response
    {
        $this->autorizarOrdenDeEmpresa($request, $orden);

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
