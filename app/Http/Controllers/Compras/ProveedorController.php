<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Proveedor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controlador de Proveedores por Empresa (Multi-Tenant y Multi-Giro).
 * Permite a Gerentes (administrador) y Subgerentes (supervisor) listar,
 * crear, editar y activar/desactivar proveedores de su empresa activa.
 */
class ProveedorController extends Controller
{
    /**
     * Valida que el proveedor pertenezca a la empresa indicada en la ruta.
     */
    protected function validarProveedorDeEmpresa(Empresa $empresa, Proveedor $proveedor): void
    {
        abort_unless(
            (int) $proveedor->empresa_id === (int) $empresa->id,
            403,
            'El proveedor no pertenece a la empresa activa.'
        );
    }

    /**
     * Listado de proveedores de la empresa activa con búsqueda rápida.
     */
    public function index(Request $request, Empresa $empresa): View
    {
        $buscar = trim((string) $request->query('q', ''));

        $proveedores = Proveedor::query()
            ->where('empresa_id', $empresa->id)
            ->when($buscar !== '', function ($query) use ($buscar) {
                $query->where(function ($sub) use ($buscar) {
                    $sub->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('razon_social', 'like', "%{$buscar}%")
                        ->orWhere('rfc', 'like', "%{$buscar}%")
                        ->orWhere('codigo', 'like', "%{$buscar}%");
                });
            })
            ->orderByDesc('activo')
            ->orderBy('razon_social')
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('compras.proveedores.index', compact('empresa', 'proveedores', 'buscar'));
    }

    /**
     * Muestra el formulario para registrar un nuevo proveedor en la empresa activa.
     */
    public function create(Request $request, Empresa $empresa): View
    {
        $retorno = (string) $request->query('retorno', '');

        return view('compras.proveedores.create', compact('empresa', 'retorno'));
    }

    /**
     * Guarda un nuevo proveedor vinculado estrictamente a la empresa activa.
     */
    public function store(Request $request, Empresa $empresa): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'codigo' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('proveedores', 'codigo')
                    ->where(fn ($q) => $q->where('empresa_id', $empresa->id)),
            ],
            'nombre' => ['required', 'string', 'max:150'],
            'razon_social' => ['nullable', 'string', 'max:200'],
            'rfc' => ['nullable', 'string', 'max:20'],
            'contacto' => ['nullable', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'correo' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'retorno' => ['nullable', 'string', 'max:50'],
        ]);

        $codigoGenerado = ! empty($validated['codigo'])
            ? strtoupper(trim($validated['codigo']))
            : 'PROV-' . strtoupper(substr(uniqid(), -6));

        $proveedor = Proveedor::create([
            'empresa_id' => $empresa->id,
            'codigo' => $codigoGenerado,
            'nombre' => trim($validated['nombre']),
            'razon_social' => ! empty($validated['razon_social'])
                ? trim($validated['razon_social'])
                : trim($validated['nombre']),
            'rfc' => ! empty($validated['rfc']) ? strtoupper(trim($validated['rfc'])) : null,
            'contacto' => $validated['contacto'] ?? null,
            'telefono' => $validated['telefono'] ?? null,
            'correo' => $validated['correo'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'notas' => $validated['notas'] ?? null,
            'activo' => true,
            'creado_por_id' => $user?->id,
            'actualizado_por_id' => $user?->id,
        ]);

        // Si se abrió desde Crear Orden de Compra, permite regresar directo al formulario
        if (($validated['retorno'] ?? '') === 'orden_compra') {
            return redirect()
                ->route('compras.ordenes.create', ['proveedor_id' => $proveedor->id])
                ->with('status', "Proveedor {$proveedor->nombre} registrado y listo para seleccionar.");
        }

        return redirect()
            ->route('operacion.proveedores.index', $empresa->id)
            ->with('status', "Proveedor {$proveedor->nombre} registrado correctamente.");
    }

    /**
     * Muestra el formulario de edición de un proveedor de la empresa activa.
     */
    public function edit(Empresa $empresa, Proveedor $proveedor): View
    {
        $this->validarProveedorDeEmpresa($empresa, $proveedor);

        return view('compras.proveedores.edit', compact('empresa', 'proveedor'));
    }

    /**
     * Actualiza los datos de un proveedor existente de la empresa activa.
     */
    public function update(Request $request, Empresa $empresa, Proveedor $proveedor): RedirectResponse
    {
        $this->validarProveedorDeEmpresa($empresa, $proveedor);
        $user = $request->user();

        $validated = $request->validate([
            'codigo' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('proveedores', 'codigo')
                    ->where(fn ($q) => $q->where('empresa_id', $empresa->id))
                    ->ignore($proveedor->id),
            ],
            'nombre' => ['required', 'string', 'max:150'],
            'razon_social' => ['nullable', 'string', 'max:200'],
            'rfc' => ['nullable', 'string', 'max:20'],
            'contacto' => ['nullable', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'correo' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $activoNuevo = $request->boolean('activo', (bool) $proveedor->activo);

        $proveedor->update([
            'codigo' => ! empty($validated['codigo'])
                ? strtoupper(trim($validated['codigo']))
                : $proveedor->codigo,
            'nombre' => trim($validated['nombre']),
            'razon_social' => ! empty($validated['razon_social'])
                ? trim($validated['razon_social'])
                : trim($validated['nombre']),
            'rfc' => ! empty($validated['rfc']) ? strtoupper(trim($validated['rfc'])) : null,
            'contacto' => $validated['contacto'] ?? null,
            'telefono' => $validated['telefono'] ?? null,
            'correo' => $validated['correo'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'notas' => $validated['notas'] ?? null,
            'activo' => $activoNuevo,
            'actualizado_por_id' => $user?->id,
            'desactivado_por_id' => ! $activoNuevo ? $user?->id : null,
            'desactivado_at' => ! $activoNuevo ? now() : null,
        ]);

        return redirect()
            ->route('operacion.proveedores.index', $empresa->id)
            ->with('status', "Proveedor {$proveedor->nombre} actualizado correctamente.");
    }

    /**
     * Activa o desactiva un proveedor de la empresa activa.
     */
    public function toggleEstado(Request $request, Empresa $empresa, Proveedor $proveedor): RedirectResponse
    {
        $this->validarProveedorDeEmpresa($empresa, $proveedor);
        $user = $request->user();

        $nuevoEstado = ! (bool) $proveedor->activo;

        $proveedor->update([
            'activo' => $nuevoEstado,
            'actualizado_por_id' => $user?->id,
            'desactivado_por_id' => ! $nuevoEstado ? $user?->id : null,
            'desactivado_at' => ! $nuevoEstado ? now() : null,
            'motivo_desactivacion' => ! $nuevoEstado
                ? ($request->input('motivo_desactivacion') ?: 'Desactivado desde catálogo de proveedores')
                : null,
        ]);

        return back()->with(
            'status',
            $nuevoEstado
                ? "Proveedor {$proveedor->nombre} activado."
                : "Proveedor {$proveedor->nombre} desactivado."
        );
    }
}
