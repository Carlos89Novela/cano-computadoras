<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServicioRequest;
use App\Http\Requests\Admin\UpdateServicioRequest;
use App\Models\Empresa;
use App\Models\Servicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador administrativo del Catálogo de Servicios.
 *
 * Administra el alta, edición, consulta y baja/desactivación de servicios
 * técnicos (ej. cómputo, telefonía, taller). Restringe su uso a empresas
 * cuyo `giro_comercial` corresponda a servicios, tecnología o taller.
 */
class ServicioController extends Controller
{
    /**
     * Muestra el listado de servicios registrados ordenados alfabéticamente.
     */
    public function index(Request $request): View
    {
        $this->validarGiroServicios($request);

        $servicios = Servicio::query()
            ->orderBy('nombre')
            ->get();

        return view('admin.servicios.index', compact('servicios'));
    }

    /**
     * Muestra el formulario para crear un nuevo servicio.
     */
    public function create(Request $request): View
    {
        $this->validarGiroServicios($request);

        return view('admin.servicios.create');
    }

    /**
     * Guarda un nuevo servicio en el catálogo a partir de datos validados.
     */
    public function store(
        StoreServicioRequest $request
    ): RedirectResponse {
        $this->validarGiroServicios($request);

        Servicio::query()->create(
            $request->validated()
        );

        return redirect()
            ->route('admin.servicios.index')
            ->with(
                'success',
                'Servicio registrado correctamente.'
            );
    }

    /**
     * Muestra el formulario de edición para un servicio existente.
     */
    public function edit(Request $request, Servicio $servicio): View
    {
        $this->validarGiroServicios($request);

        return view(
            'admin.servicios.edit',
            compact('servicio')
        );
    }

    /**
     * Actualiza los datos de un servicio existente.
     */
    public function update(
        Request $request,
        UpdateServicioRequest $updateRequest,
        Servicio $servicio
    ): RedirectResponse {
        $this->validarGiroServicios($request);

        $servicio->update(
            $updateRequest->validated()
        );

        return redirect()
            ->route('admin.servicios.index')
            ->with(
                'success',
                'Servicio actualizado correctamente.'
            );
    }

    /**
     * Elimina un servicio si no tiene órdenes de servicio asociadas;
     * en caso contrario lo desactiva para conservar historial.
     */
    public function destroy(
        Request $request,
        Servicio $servicio
    ): RedirectResponse {
        $this->validarGiroServicios($request);

        if ($servicio->ordenesServicio()->exists()) {
            $servicio->update([
                'activo' => false,
            ]);

            return redirect()
                ->route('admin.servicios.index')
                ->with(
                    'success',
                    'El servicio tiene reparaciones relacionadas y fue desactivado.'
                );
        }

        $servicio->delete();

        return redirect()
            ->route('admin.servicios.index')
            ->with(
                'success',
                'Servicio eliminado correctamente.'
            );
    }

    /**
     * Resuelve la empresa activa (sesión, sucursal activa o respaldo activo)
     * y verifica que su `giro_comercial` aplique para el catálogo de servicios.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function validarGiroServicios(Request $request): void
    {
        $user = $request->user();
        $empresa = null;

        // 1. Priorizar la empresa activa guardada en sesión
        $sessionEmpresaId = session('empresa_activa_id') ?? session('empresa_id');
        if (! empty($sessionEmpresaId)) {
            $empresa = Empresa::query()->find((int) $sessionEmpresaId);
        }

        // 2. Si no hay sesión, resolver mediante la sucursal activa asignada al usuario
        if (! $empresa && $user && method_exists($user, 'sucursales')) {
            $empresa = $user->sucursales()
                ->wherePivot('activo', true)
                ->orderByDesc('sucursal_usuario.es_principal')
                ->with('empresa')
                ->first()?->empresa;
        }

        // 3. Respaldo final: primera empresa activa registrada
        if (! $empresa) {
            $empresa = Empresa::query()->where('activo', true)->first();
        }

        $giro = strtolower((string) ($empresa?->giro_comercial ?? ''));
        $permitido = (bool) preg_match(
            '/computador|tecnolog|taller|reparac|servicio|celular|electron/',
            $giro
        );

        abort_unless(
            $permitido,
            403,
            'El catálogo de servicios no aplica para el giro comercial de la empresa activa.'
        );
    }
}