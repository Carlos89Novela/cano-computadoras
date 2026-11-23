<?php

namespace App\Http\Controllers\Supervisor;

use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador del Panel de Control de Supervisión Operativa.
 *
 * Resuelve el contexto multi-tenant y el giro comercial de la empresa activa:
 * - Para giro de restaurante (`restaurantes_alimentos` / `restaurante_cafeteria`),
 *   renderiza la vista operativa completa (`dashboard`) con el encabezado de
 *   supervisión del restaurante.
 * - Para giro de taller / tecnología, consolida métricas y colas de trabajo de
 *   reparaciones (asignación, revisión de cotizaciones y entregas).
 */
class DashboardController extends Controller
{
    /**
     * Muestra la vista de supervisión acorde al giro comercial de la empresa activa.
     */
    public function index(Request $request): View
    {
        /** @var User|null $usuario */
        $usuario = $request->user();
        $empresa = $this->resolverEmpresaActiva($request, $usuario);

        $esRestaurante = $empresa instanceof Empresa && (
            (method_exists($empresa, 'esRestaurante') && $empresa->esRestaurante())
            || in_array((string) $empresa->giro_comercial, ['restaurantes_alimentos', 'restaurante_cafeteria'], true)
        );

        // En restaurantes, utilizar la vista operativa completa con distintivo de Supervisor
        if ($esRestaurante) {
            return view('dashboard', [
                'empresa' => $empresa,
                'giro' => $empresa->giro_comercial,
            ]);
        }

        // ---------------------------------------------------------------------
        // 1. Métricas Globales de Operación (Taller / Reparaciones)
        // ---------------------------------------------------------------------

        // Conteo histórico absoluto de órdenes recibidas en el sistema
        $totalOrdenes = OrdenServicio::query()
            ->count();

        // Conteo de órdenes activas (excluyendo estados terminales como entregado o cancelado)
        $ordenesActivas = OrdenServicio::query()
            ->whereNotIn(
                'estado',
                EstadoOrden::finalizados()
            )
            ->count();

        // Estados operativos en los cuales una orden ya no requiere asignación técnica activa
        $estadosSinAsignacionRequerida = [
            ...EstadoOrden::finalizados(),
            EstadoOrden::LISTO_PARA_ENTREGA->value,
        ];

        // Conteo de órdenes que requieren intervención técnica pero carecen de técnico asignado activo
        $ordenesSinAsignar = OrdenServicio::query()
            ->whereNotIn(
                'estado',
                $estadosSinAsignacionRequerida
            )
            ->whereDoesntHave(
                'asignaciones',
                function (Builder $consulta): void {
                    $consulta->where('activo', true);
                }
            )
            ->count();

        // Inicializador de cierres pendientes para compatibilidad de interfaz
        $cierresPendientes = 0;

        // Conteo de cotizaciones técnicas que han sido enviadas a revisión por un empleado y esperan decisión
        $cotizacionesPendientes = OrdenServicio::query()
            ->where(
                'estado_revision_cotizacion',
                EstadoRevisionCotizacion::PENDIENTE->value
            )
            ->count();

        // ---------------------------------------------------------------------
        // 2. Disponibilidad y Carga Actual de Empleados Técnicos
        // ---------------------------------------------------------------------

        // Obtiene todos los usuarios con rol de empleado técnico junto con el conteo de asignaciones activas
        $empleados = User::role('empleado')
            ->withCount([
                'asignacionesComoEmpleado as carga_activa' => function (
                    Builder $consulta
                ): void {
                    $consulta->where('activo', true);
                },
            ])
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        // ---------------------------------------------------------------------
        // 3. Colas de Trabajo Prioritarias
        // ---------------------------------------------------------------------

        // Órdenes sin asignar ordenadas por antigüedad de ingreso (FIFO) para evitar rezagos en recepción
        $ordenesPendientes = OrdenServicio::query()
            ->with([
                'user:id,name',
                'equipo:id,marca,modelo',
                'servicio:id,nombre',
            ])
            ->whereNotIn(
                'estado',
                $estadosSinAsignacionRequerida
            )
            ->whereDoesntHave(
                'asignaciones',
                function (Builder $consulta): void {
                    $consulta->where('activo', true);
                }
            )
            ->oldest('fecha_ingreso')
            ->limit(20)
            ->get();

        // Órdenes que actualmente tienen un técnico asignado y se encuentran en progreso operativo
        $ordenesAsignadas = OrdenServicio::query()
            ->with([
                'user:id,name',
                'equipo:id,marca,modelo',
                'servicio:id,nombre',
                'asignacionActiva.empleado:id,name',
            ])
            ->whereNotIn(
                'estado',
                EstadoOrden::finalizados()
            )
            ->whereHas(
                'asignaciones',
                function (Builder $consulta): void {
                    $consulta->where('activo', true);
                }
            )
            ->latest('id')
            ->limit(20)
            ->get();

        // Órdenes que concluyeron su proceso técnico, cuentan con autorización y costo final, listas para entrega física
        $ordenesListasEntrega = OrdenServicio::query()
            ->with([
                'user:id,name,email',
                'equipo:id,marca,modelo,tipo,numero_serie',
            ])
            ->where(
                'estado',
                EstadoOrden::LISTO_PARA_ENTREGA->value
            )
            ->where(
                'autorizacion',
                EstadoAutorizacion::AUTORIZADA->value
            )
            ->where(
                'estado_revision_cotizacion',
                EstadoRevisionCotizacion::APROBADA->value
            )
            ->whereNotNull('costo_final')
            ->whereNull('fecha_entrega')
            ->oldest('updated_at')
            ->limit(20)
            ->get();

        // Renderiza el panel de supervisión para taller / reparaciones
        return view(
            'supervisor.dashboard',
            compact(
                'empresa',
                'totalOrdenes',
                'ordenesActivas',
                'ordenesSinAsignar',
                'cotizacionesPendientes',
                'cierresPendientes',
                'empleados',
                'ordenesPendientes',
                'ordenesAsignadas',
                'ordenesListasEntrega'
            )
        );
    }

    /**
     * Resuelve la empresa activa desde sesión, sucursal asignada o primera empresa activa.
     */
    protected function resolverEmpresaActiva(Request $request, ?User $usuario): ?Empresa
    {
        $sessionEmpresaId = $request->session()->get('empresa_activa_id')
            ?? $request->session()->get('empresa_id');

        if (! empty($sessionEmpresaId)) {
            $empresa = Empresa::query()->find((int) $sessionEmpresaId);
            if ($empresa instanceof Empresa) {
                return $empresa;
            }
        }

        if ($usuario && method_exists($usuario, 'sucursales')) {
            $sucursalActiva = $usuario->sucursales()
                ->wherePivot('activo', true)
                ->orderByDesc('sucursal_usuario.es_principal')
                ->with('empresa')
                ->first();

            if ($sucursalActiva?->empresa instanceof Empresa) {
                $request->session()->put('empresa_activa_id', $sucursalActiva->empresa->id);

                return $sucursalActiva->empresa;
            }
        }

        return Empresa::query()->where('activo', true)->first();
    }
}