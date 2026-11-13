<?php

namespace App\Services\Marketing;

use App\Models\Cliente;
use App\Models\Promocion;
use Illuminate\Support\Collection;

class CalcularPromocionesService
{
    /**
     * Evalúa qué promociones vigentes aplican a un listado de partidas.
     *
     * @param  array<int, array{producto_id: int, cantidad: float|int, precio_unitario: float}>  $items
     * @return array{
     *     descuento_total: float,
     *     promociones: array<int, array{id: int, nombre: string, tipo: string, ahorro: float}>
     * }
     */
    public function evaluar(int $empresaId, array $items, ?Cliente $cliente = null): array
    {
        if (empty($items)) {
            return ['descuento_total' => 0.0, 'promociones' => []];
        }

        /** @var Collection<int, Promocion> $promocionesVigentes */
        $promocionesVigentes = Promocion::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->with(['items'])
            ->get()
            ->filter(fn (Promocion $p) => $p->estaVigenteAhora());

        $aplicadas = [];
        $descuentoTotal = 0.0;

        foreach ($promocionesVigentes as $promo) {
            if ($promo->solo_clientes_destacados && $cliente?->nivel_lealtad !== 'destacado') {
                continue;
            }

            $idsParticipantes = $promo->items
                ->pluck('producto_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();

            $lineasElegibles = ! empty($idsParticipantes)
                ? array_values(array_filter($items, fn ($it) => in_array((int) $it['producto_id'], $idsParticipantes, true)))
                : $items;

            if (empty($lineasElegibles)) {
                continue;
            }

            $totalUnidades = array_reduce($lineasElegibles, fn ($acc, $i) => $acc + (float) $i['cantidad'], 0.0);
            $ahorro = 0.0;

            if ($promo->tipo === '2x1' && $totalUnidades >= 2) {
                $pares = (int) floor($totalUnidades / 2);
                $precioMenor = min(array_map(fn ($i) => (float) $i['precio_unitario'], $lineasElegibles));
                $ahorro = $pares * $precioMenor;
            } elseif ($promo->tipo === 'segundo_al_porcentaje' && $totalUnidades >= 2) {
                $pares = (int) floor($totalUnidades / 2);
                $precioMenor = min(array_map(fn ($i) => (float) $i['precio_unitario'], $lineasElegibles));
                $ahorro = $pares * ($precioMenor * ((float) $promo->valor_descuento / 100));
            } elseif ($promo->tipo === 'descuento_porcentaje') {
                $subtotalElegible = array_reduce($lineasElegibles, fn ($acc, $i) => $acc + ((float) $i['precio_unitario'] * (float) $i['cantidad']), 0.0);
                $ahorro = $subtotalElegible * ((float) $promo->valor_descuento / 100);
            } elseif ($promo->tipo === 'precio_fijo_combo' && $totalUnidades >= count($idsParticipantes) && ! empty($idsParticipantes)) {
                $sumaOriginal = array_reduce($lineasElegibles, fn ($acc, $i) => $acc + (float) $i['precio_unitario'], 0.0);
                if ($sumaOriginal > (float) $promo->precio_combo) {
                    $ahorro = $sumaOriginal - (float) $promo->precio_combo;
                }
            }

            $ahorro = round($ahorro, 2);

            if ($ahorro > 0) {
                $aplicadas[] = [
                    'id' => $promo->id,
                    'nombre' => $promo->nombre,
                    'tipo' => $promo->tipo,
                    'ahorro' => $ahorro,
                ];
                $descuentoTotal = round($descuentoTotal + $ahorro, 2);
            }
        }

        return [
            'descuento_total' => $descuentoTotal,
            'promociones' => $aplicadas,
        ];
    }
}