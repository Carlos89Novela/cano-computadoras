<?php

namespace App\Http\Controllers\Marketing;

// ---------------------------------------------------------
// IMPORTACIONES DE CONTROLADORES, MODELOS Y FACADES
// ---------------------------------------------------------
use App\Http\Controllers\Controller;
use App\Models\CategoriaProducto;
use App\Models\Cliente;
use App\Models\ClienteMonederoMovimiento;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\PromocionItem;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Controlador del módulo de Marketing, Lealtad y Clientes Fiscales.
 * Gestiona promociones/combos, ajustes de monedero y alta/edición de clientes
 * consumidos tanto desde el panel de marketing como desde el modal CFDI del POS.
 */
class PromocionController extends Controller
{
    // =========================================================
    // VISTA PRINCIPAL DE PROMOCIONES Y LEALTAD
    // =========================================================

    /**
     * Muestra la interfaz de gestión de promociones, combos y clientes.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $empresa = $this->resolveEmpresa($user);

        // Consultar promociones activas/inactivas de la empresa con sus ítems relacionados
        $promociones = Promocion::query()
            ->where('empresa_id', $empresa->id)
            ->with(['items.producto', 'items.categoria'])
            ->latest('id')
            ->get();

        // Consultar categorías comerciales excluyendo insumos operativos de cocina/barra/materia prima
        $categorias = CategoriaProducto::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->whereNotIn('nombre', ['Insumos de Cocina', 'Insumos de Barra', 'Materia Prima'])
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $categoriaIdsValidas = $categorias->pluck('id')->all();

        // Consultar productos activos no extras dentro de las categorías válidas
        $productos = Producto::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->where('es_extra', false)
            ->whereIn('categoria_producto_id', $categoriaIdsValidas)
            ->orderBy('nombre')
            ->get(['id', 'sku', 'nombre', 'precio_base']);

        // Consultar clientes registrados ordenados por puntos acumulados
        $clientesLealtad = Cliente::query()
            ->where('empresa_id', $empresa->id)
            ->orderByDesc('puntos_acumulados')
            ->limit(30)
            ->get();

        return view('marketing.promociones.index', [
            'empresa' => $empresa,
            'promociones' => $promociones,
            'productos' => $productos,
            'categorias' => $categorias,
            'clientesLealtad' => $clientesLealtad,
        ]);
    }

    // =========================================================
    // REGISTRO DE NUEVA PROMOCIÓN / COMBO
    // =========================================================

    /**
     * Valida y almacena una nueva promoción en la base de datos de forma transaccional.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresa = $this->resolveEmpresa($user);

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'codigo_cupon' => ['nullable', 'string', 'max:40'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'tipo' => ['required', 'string', 'in:2x1,segundo_al_porcentaje,descuento_porcentaje,precio_fijo_combo,regalo_en_compra'],
            'valor_descuento' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'precio_combo' => ['nullable', 'numeric', 'min:0'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'dias_semana' => ['nullable', 'array'],
            'dias_semana.*' => ['integer', 'min:0', 'max:6'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'date_format:H:i'],
            'solo_clientes_destacados' => ['nullable', 'boolean'],
            'puntos_extra_otorgados' => ['nullable', 'integer', 'min:0'],
            'limite_usos' => ['nullable', 'integer', 'min:1'],
            'producto_ids' => ['nullable', 'array'],
            'producto_ids.*' => ['integer', 'exists:productos,id'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias_producto,id'],
        ]);

        DB::transaction(function () use ($validated, $empresa): void {
            // Crear la promoción principal
            $promo = Promocion::query()->create([
                'empresa_id' => $empresa->id,
                'nombre' => $validated['nombre'],
                'codigo_cupon' => ! empty($validated['codigo_cupon']) ? strtoupper(trim($validated['codigo_cupon'])) : null,
                'descripcion' => $validated['descripcion'] ?? null,
                'tipo' => $validated['tipo'],
                'valor_descuento' => $validated['tipo'] === '2x1' ? 100 : ($validated['valor_descuento'] ?? 0),
                'precio_combo' => $validated['precio_combo'] ?? null,
                'fecha_inicio' => $validated['fecha_inicio'] ?? null,
                'fecha_fin' => $validated['fecha_fin'] ?? null,
                'dias_semana' => $validated['dias_semana'] ?? null,
                'hora_inicio' => ! empty($validated['hora_inicio']) ? $validated['hora_inicio'].':00' : null,
                'hora_fin' => ! empty($validated['hora_fin']) ? $validated['hora_fin'].':00' : null,
                'solo_clientes_destacados' => (bool) ($validated['solo_clientes_destacados'] ?? false),
                'puntos_extra_otorgados' => (int) ($validated['puntos_extra_otorgados'] ?? 0),
                'limite_usos' => $validated['limite_usos'] ?? null,
                'activo' => true,
            ]);

            // Vincular productos participantes si fueron seleccionados
            if (! empty($validated['producto_ids'])) {
                foreach ($validated['producto_ids'] as $prodId) {
                    PromocionItem::query()->create([
                        'promocion_id' => $promo->id,
                        'producto_id' => $prodId,
                        'cantidad' => 1,
                        'rol' => 'participante',
                    ]);
                }
            }

            // Vincular categoría participante si fue seleccionada
            if (! empty($validated['categoria_id'])) {
                PromocionItem::query()->create([
                    'promocion_id' => $promo->id,
                    'categoria_producto_id' => $validated['categoria_id'],
                    'cantidad' => 1,
                    'rol' => 'participante',
                ]);
            }
        });

        return back()->with('success', 'Promoción / Combo creado y activado correctamente.');
    }

    // =========================================================
    // ACTUALIZACIÓN DE PROMOCIÓN
    // =========================================================

    /**
     * Actualiza una promoción o combo existente y reemplaza sus ítems participantes.
     */
    public function update(Request $request, Promocion $promocion): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'tipo' => ['required', 'in:2x1,segundo_al_porcentaje,descuento_porcentaje,precio_fijo_combo'],
            'valor_descuento' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'precio_combo' => ['nullable', 'numeric', 'min:0'],
            'codigo_cupon' => ['nullable', 'string', 'max:50'],
            'puntos_extra_otorgados' => ['nullable', 'integer', 'min:0'],
            'dias_semana' => ['nullable', 'array'],
            'hora_inicio' => ['nullable', 'string'],
            'hora_fin' => ['nullable', 'string'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date'],
            'producto_ids' => ['nullable', 'array'],
            'categoria_id' => ['nullable', 'integer'],
            'solo_clientes_destacados' => ['nullable', 'boolean'],
        ]);

        // Si se marcan los 7 días de la semana, se guarda null para aplicar todos los días
        $diasLimpios = ! empty($validated['dias_semana']) ? array_values(array_unique(array_map('intval', $validated['dias_semana']))) : null;
        if (is_array($diasLimpios) && count($diasLimpios) >= 7) {
            $diasLimpios = null;
        }

        $promocion->update([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'tipo' => $validated['tipo'],
            'valor_descuento' => (float) ($validated['valor_descuento'] ?? 0),
            'precio_combo' => isset($validated['precio_combo']) ? (float) $validated['precio_combo'] : null,
            'codigo_cupon' => ! empty($validated['codigo_cupon']) ? strtoupper(trim($validated['codigo_cupon'])) : null,
            'puntos_extra_otorgados' => (int) ($validated['puntos_extra_otorgados'] ?? 0),
            'dias_semana' => $diasLimpios,
            'hora_inicio' => $validated['hora_inicio'] ?: null,
            'hora_fin' => $validated['hora_fin'] ?: null,
            'fecha_inicio' => $validated['fecha_inicio'] ?: null,
            'fecha_fin' => $validated['fecha_fin'] ?: null,
            'solo_clientes_destacados' => $request->boolean('solo_clientes_destacados'),
        ]);

        // Reconstruir ítems participantes de la promoción
        $promocion->items()->delete();

        $productoIds = array_filter(array_map('intval', $validated['producto_ids'] ?? []));
        $categoriaId = (int) ($validated['categoria_id'] ?? 0);

        if (! empty($productoIds)) {
            foreach ($productoIds as $prodId) {
                $promocion->items()->create([
                    'producto_id' => $prodId,
                    'categoria_id' => null,
                    'cantidad' => 1,
                ]);
            }
        } elseif ($categoriaId > 0) {
            $promocion->items()->create([
                'producto_id' => null,
                'categoria_id' => $categoriaId,
                'cantidad' => 1,
            ]);
        }

        return back()->with('success', "Promoción «{$promocion->nombre}» actualizada correctamente.");
    }

    // =========================================================
    // OPERACIONES RÁPIDAS (TOGGLE Y ELIMINACIÓN)
    // =========================================================

    /**
     * Activa o desactiva una promoción.
     */
    public function toggleEstado(Promocion $promocion): RedirectResponse
    {
        $promocion->update(['activo' => ! $promocion->activo]);

        return back()->with('success', 'Estado de la promoción actualizado.');
    }

    /**
     * Elimina una promoción de la base de datos.
     */
    public function destroy(Promocion $promocion): RedirectResponse
    {
        $promocion->delete();

        return back()->with('success', 'Promoción eliminada.');
    }

    // =========================================================
    // PROGRAMA DE LEALTAD Y MONEDERO DE CLIENTES
    // =========================================================

    /**
     * Actualiza el nivel de lealtad, saldo de monedero o puntos de un cliente
     * y registra el movimiento en el historial cuando aplica.
     */
    public function actualizarLealtadCliente(Request $request, Cliente $cliente): RedirectResponse
    {
        $validated = $request->validate([
            'nivel_lealtad' => ['required', 'in:regular,frecuente,destacado'],
            'abono_monedero' => ['nullable', 'numeric'],
            'puntos_ajuste' => ['nullable', 'integer'],
            'concepto' => ['nullable', 'string', 'max:200'],
        ]);

        $abono = (float) ($validated['abono_monedero'] ?? 0);
        $puntos = (int) ($validated['puntos_ajuste'] ?? 0);

        DB::transaction(function () use ($cliente, $validated, $abono, $puntos): void {
            $cliente->update([
                'nivel_lealtad' => $validated['nivel_lealtad'],
                'saldo_monedero' => max(0, (float) $cliente->saldo_monedero + $abono),
                'puntos_acumulados' => max(0, (int) $cliente->puntos_acumulados + $puntos),
            ]);

            if ($abono !== 0.0 || $puntos !== 0) {
                ClienteMonederoMovimiento::query()->create([
                    'empresa_id' => $cliente->empresa_id,
                    'cliente_id' => $cliente->id,
                    'tipo' => 'ajuste_manual',
                    'monto_monedero' => $abono,
                    'puntos' => $puntos,
                    'concepto' => $validated['concepto'] ?? 'Ajuste manual desde panel de lealtad',
                ]);
            }
        });

        return back()->with('success', "Perfil de lealtad de {$cliente->nombre} actualizado.");
    }

    // =========================================================
    // ALTA / EDICIÓN DE CLIENTE FISCAL Y DE LEALTAD (MARKETING / POS CFDI)
    // =========================================================

    /**
     * Crea o actualiza un cliente fiscal / de lealtad evitando duplicados por ID o por RFC,
     * y persiste sus datos de contacto y catálogos SAT.
     */
    public function storeCliente(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $empresa = $user ? $this->resolveEmpresa($user) : null;

        // Validar datos generales y fiscales recibidos desde Marketing o desde el POS
        $validated = $request->validate([
            'id' => ['nullable', 'integer', 'exists:clientes,id'],
            'nombre' => ['required', 'string', 'max:150'],
            'rfc' => ['nullable', 'string', 'max:13'],
            'nivel_lealtad' => ['nullable', 'string', 'in:regular,frecuente,destacado'],
            'regimen_fiscal' => ['nullable', 'string', 'max:10'],
            'uso_cfdi' => ['nullable', 'string', 'max:10'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'email' => ['nullable', 'email', 'max:150'],
            'telefono_whatsapp' => ['nullable', 'string', 'max:30'],
            'saldo_monedero' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Normalizar RFC en mayúsculas y limpiar cadenas de texto
        $rfcNormalizado = ! empty($validated['rfc']) ? strtoupper(trim((string) $validated['rfc'])) : null;

        $atributos = [
            'nombre' => trim((string) $validated['nombre']),
            'rfc' => $rfcNormalizado,
            'nivel_lealtad' => $validated['nivel_lealtad'] ?? 'regular',
            'codigo_postal' => isset($validated['codigo_postal']) && trim((string) $validated['codigo_postal']) !== ''
                ? trim((string) $validated['codigo_postal'])
                : null,
            'telefono_whatsapp' => isset($validated['telefono_whatsapp']) && trim((string) $validated['telefono_whatsapp']) !== ''
                ? trim((string) $validated['telefono_whatsapp'])
                : null,
        ];

        // Asignar empresa_id en registros nuevos si la columna existe
        if ($empresa && Schema::hasColumn('clientes', 'empresa_id')) {
            $atributos['empresa_id'] = $empresa->id;
        }

        // Asignar correo y datos fiscales SAT si existen las columnas en la tabla clientes
        if (Schema::hasColumn('clientes', 'email')) {
            $atributos['email'] = isset($validated['email']) && trim((string) $validated['email']) !== ''
                ? trim((string) $validated['email'])
                : null;
        }
        if (Schema::hasColumn('clientes', 'regimen_fiscal')) {
            $atributos['regimen_fiscal'] = $validated['regimen_fiscal'] ?? '616';
        }
        if (Schema::hasColumn('clientes', 'uso_cfdi')) {
            $atributos['uso_cfdi'] = $validated['uso_cfdi'] ?? 'G03';
        }
        if (isset($validated['saldo_monedero']) && Schema::hasColumn('clientes', 'saldo_monedero')) {
            $atributos['saldo_monedero'] = $validated['saldo_monedero'];
        }

        // 1. Si viene 'id' (desde el botón "Ver / Editar"), actualizar directamente ese registro
        if (! empty($validated['id'])) {
            $cliente = Cliente::query()->findOrFail((int) $validated['id']);
            // Conservar su empresa_id original si ya tenía uno asignado
            if (Schema::hasColumn('clientes', 'empresa_id') && ! empty($cliente->empresa_id)) {
                unset($atributos['empresa_id']);
            }
            $cliente->update($atributos);
        }
        // 2. Si no viene 'id' pero sí un RFC fiscal distinto a genérico, actualizar si ya existe o crear
        elseif ($rfcNormalizado && ! in_array($rfcNormalizado, ['XAXX010101000', 'XEXX010101000'], true)) {
            $clienteExistente = Cliente::query()
                ->whereRaw('UPPER(TRIM(rfc)) = ?', [$rfcNormalizado])
                ->first();

            if ($clienteExistente) {
                if (Schema::hasColumn('clientes', 'empresa_id') && ! empty($clienteExistente->empresa_id)) {
                    unset($atributos['empresa_id']);
                }
                $clienteExistente->update($atributos);
                $cliente = $clienteExistente;
            } else {
                $cliente = Cliente::query()->create($atributos);
            }
        }
        // 3. Caso sin RFC o público general: crear nuevo registro
        else {
            $cliente = Cliente::query()->create($atributos);
        }

        // Responder JSON si la petición viene desde fetch() en el POS
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => 'Cliente actualizado correctamente sin duplicados.',
                'cliente' => $cliente->fresh(),
            ]);
        }

        return back()->with('success', 'Cliente guardado correctamente.');
    }

    // =========================================================
    // ENDPOINTS API / JSON
    // =========================================================

    /**
     * Retorna las promociones activas y vigentes para el POS.
     */
    public function vigentesApi(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresa = $this->resolveEmpresa($user);

        $promos = Promocion::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->with(['items.producto', 'items.categoria'])
            ->get()
            ->filter(fn (Promocion $p) => $p->estaVigente())
            ->values();

        return response()->json($promos);
    }

    // =========================================================
    // MÉTODOS AUXILIARES PRIVADOS
    // =========================================================

    /**
     * Resuelve la empresa activa asociada al usuario o sucursal.
     */
    private function resolveEmpresa(User $user): Empresa
    {
        /** @var Sucursal|null $sucursalActiva */
        $sucursalActiva = $user->sucursales()->with('empresa')->where('sucursales.activo', true)->first();
        $empresaSucursal = $sucursalActiva !== null ? $sucursalActiva->empresa : null;

        return Empresa::query()->where('id', 3)->first()
            ?? $empresaSucursal
            ?? $user->empresa
            ?? Empresa::query()->where('activo', true)->firstOrFail();
    }
}
