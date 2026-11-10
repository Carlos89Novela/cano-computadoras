<?php

namespace App\Services\Restaurante;

use App\Models\Comanda;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\ProductoReceta;
use App\Models\Sucursal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;

class RestauranteReporteService
{
    /**
     * @return array{
     *     top_platillos: array<int, array{nombre: string, sku: string, cantidad: float, total: float}>,
     *     metricas_kds: array<string, array{cantidad: int, promedio_minutos: float}>,
     *     margenes_recetas: array<int, array{nombre: string, precio_venta: float, costo_teorico: float, margen_porcentaje: float}>,
     *     mermas_acumuladas: array<int, array{insumo: string, unidad: string, merma_total: float, costo_merma: float}>
     * }
     */
    public function obtenerMetricasPeriodo(Empresa $empresa, Sucursal $sucursal, ?Carbon $fechaInicio = null, ?Carbon $fechaFin = null): array
    {
        $inicio = $fechaInicio ?? now()->startOfMonth();
        $fin = $fechaFin ?? now()->endOfDay();

        // 1. Top de platillos más vendidos usando DB::table para evitar conflictos de propiedades dinámicas en el modelo
        /** @var array<int, stdClass> $rawTopPlatillos */ $rawTopPlatillos = DB::table('venta_detalles')
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->where('ventas.empresa_id', $empresa->id)
            ->where('ventas.sucursal_id', $sucursal->id)
            ->where('ventas.estado', 'completada')
            ->whereBetween('ventas.created_at', [$inicio, $fin])
            ->select([
                'venta_detalles.descripcion_producto as nombre',
                'venta_detalles.sku',
                DB::raw('SUM(venta_detalles.cantidad) as total_unidades'),
                DB::raw('SUM(venta_detalles.total) as total_ventas'),
            ])
            ->groupBy('venta_detalles.sku', 'venta_detalles.descripcion_producto')
            ->orderByDesc('total_unidades')
            ->limit(10)
            ->get()
            ->all();

        $topPlatillos = array_map(static fn (stdClass $r): array => [
            'nombre' => (string) $r->nombre,
            'sku' => (string) $r->sku,
            'cantidad' => (float) $r->total_unidades,
            'total' => (float) $r->total_ventas,
        ], $rawTopPlatillos);

        // 2. Tiempos promedio de preparación en pantallas KDS
        $comandasCompletadas = Comanda::query()
            ->where('empresa_id', $empresa->id)
            ->where('sucursal_id', $sucursal->id)
            ->where('estado', 'completada')
            ->whereNotNull('finalizada_at')
            ->whereBetween('created_at', [$inicio, $fin])
            ->with('items')
            ->get();

        $metricasKds = [];
        foreach (['cocina', 'barra', 'parrilla', 'postres'] as $estacion) {
            $metricasKds[$estacion] = [
                'cantidad' => 0,
                'promedio_minutos' => 0.0,
            ];
        }

        /** @var array<string, array{total_min: float, conteo: int}> $acumuladores */ $acumuladores = [];
        foreach ($comandasCompletadas as $cmd) {
            if ($cmd->finalizada_at === null) {
                continue;
            }

            $minutos = max(1.0, (float) $cmd->enviada_a_cocina_at->diffInMinutes($cmd->finalizada_at));
            foreach ($cmd->items as $item) {
                $est = $item->estacion_cocina;
                if (! isset($acumuladores[$est])) {
                    $acumuladores[$est] = ['total_min' => 0.0, 'conteo' => 0];
                }$acumuladores[$est]['total_min'] += $minutos;
                $acumuladores[$est]['conteo'] += 1;
            }
        }

        foreach ($acumuladores as $estacion => $data) {
            $conteo = $data['conteo'];
            $metricasKds[$estacion] = [
                'cantidad' => $conteo,
                'promedio_minutos' => round($data['total_min'] / $conteo, 1),
            ];
        }

        // 3. Márgenes y costo teórico de recetas
        $recetas = ProductoReceta::query()
            ->where('empresa_id', $empresa->id)
            ->with(['producto', 'ingredientes.insumo'])
            ->get();

        $margenesRecetas = [];
        foreach ($recetas as $receta) {
            $costoTotal = 0.0;
            foreach ($receta->ingredientes as $ing) {
                $factorMerma = 1 + (((float) $ing->porcentaje_merma) / 100);
                $costoInsumo = (float) $ing->insumo->costo_referencia;
                $costoTotal += (((float) $ing->cantidad_requerida) * $factorMerma * $costoInsumo);
            }

            $precioVenta = (float) $receta->producto->precio_base;
            $margenPorc = $precioVenta > 0 ? round((($precioVenta - $costoTotal) / $precioVenta) * 100, 1) : 0.0;

            $margenesRecetas[] = [
                'nombre' => $receta->producto->nombre,
                'precio_venta' => $precioVenta,
                'costo_teorico' => round($costoTotal, 2), 'margen_porcentaje' => $margenPorc,
            ];
        }

        // 4. Mermas acumuladas de materia prima
        $mermasAcumuladas = [];
        /** @var array<int, stdClass> $itemsPreparados */ $itemsPreparados = DB::table('comanda_items')
            ->join('comandas', 'comandas.id', '=', 'comanda_items.comanda_id')
            ->where('comandas.empresa_id', $empresa->id)
            ->where('comandas.sucursal_id', $sucursal->id)
            ->where('comanda_items.estado', 'listo')
            ->whereBetween('comanda_items.created_at', [$inicio, $fin])
            ->select('comanda_items.producto_id', DB::raw('SUM(comanda_items.cantidad) as total_piezas'))
            ->groupBy('comanda_items.producto_id')
            ->get()
            ->all();

        $mapaMermas = [];
        foreach ($itemsPreparados as $ip) {
            /** @var Producto|null $prod */
            $prod = Producto::query()->with('receta.ingredientes.insumo')->find((int) $ip->producto_id);
            if ($prod !== null && $prod->receta !== null) {
                foreach ($prod->receta->ingredientes as $det) {
                    $insumoId = (int) $det->insumo_producto_id;
                    $mermaUnitaria = ((float) $det->cantidad_requerida) * (((float) $det->porcentaje_merma) / 100);
                    $mermaTotalLote = $mermaUnitaria * ((float) $ip->total_piezas);
                    $costoMermaLote = $mermaTotalLote * ((float) $det->insumo->costo_referencia);

                    if (! isset($mapaMermas[$insumoId])) {
                        $mapaMermas[$insumoId] = [
                            'insumo' => $det->insumo->nombre,
                            'unidad' => $det->insumo->unidad_medida,
                            'merma_total' => 0.0,
                            'costo_merma' => 0.0,
                        ];
                    }

                    $mapaMermas[$insumoId]['merma_total'] += $mermaTotalLote;
                    $mapaMermas[$insumoId]['costo_merma'] += $costoMermaLote;
                }
            }
        }

        foreach ($mapaMermas as $m) {
            $mermasAcumuladas[] = [
                'insumo' => $m['insumo'],
                'unidad' => $m['unidad'],
                'merma_total' => round($m['merma_total'], 4), 'costo_merma' => round($m['costo_merma'], 2),
            ];
        }

        return [
            'top_platillos' => $topPlatillos,
            'metricas_kds' => $metricasKds,
            'margenes_recetas' => $margenesRecetas,
            'mermas_acumuladas' => $mermasAcumuladas,
        ];
    }
}
