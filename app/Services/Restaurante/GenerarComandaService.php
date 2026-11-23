<?php

namespace App\Services\Restaurante;

// ---------------------------------------------------------
// IMPORTACIONES DE MODELOS Y FACADES
// ---------------------------------------------------------
use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Servicio transaccional encargado de generar comandas para cocina/barra/KDS
 * y asignar la estación correspondiente a cada ítem solicitado.
 */
class GenerarComandaService
{
    /**
     * Crea una comanda y sus ítems dentro de una transacción atómica,
     * asignando la estación KDS según receta, categoría o valor por defecto.
     *
     * @param array{
     *     empresa_id: int,
     *     sucursal_id: int,
     *     mesero_user_id: int,
     *     mesa_o_referencia: string,
     *     notas_generales?: string|null,
     *     items: array<int, array{
     *         producto_id: int,
     *         cantidad: numeric,
     *         notas_especiales?: string|null,
     *         extras?: array<int, string>|string|null
     *     }>
     * } $datos
     */
    public function ejecutar(array $datos): Comanda
    {
        $empresaId = (int) $datos['empresa_id'];
        $sucursalId = (int) $datos['sucursal_id'];
        $meseroUserId = (int) $datos['mesero_user_id'];

        if (empty($datos['items']) || ! is_array($datos['items'])) {
            throw new InvalidArgumentException('La comanda debe contener al menos un ítem.');
        }

        // ---------------------------------------------------------
        // 1. TRANSACCIÓN ATÓMICA DE BASE DE DATOS
        // Si ocurre algún error en cualquier ítem, se revierte todo.
        // ---------------------------------------------------------
        return DB::transaction(function () use ($datos, $empresaId, $sucursalId, $meseroUserId): Comanda {

            // Generar un folio consecutivo único para el día y la empresa
            $folio = $this->generarFolioUnico($empresaId);

            /** @var Comanda $comanda */
            $comanda = Comanda::query()->create([
                'empresa_id' => $empresaId,
                'sucursal_id' => $sucursalId,
                'mesero_user_id' => $meseroUserId,
                'mesa_o_referencia' => $datos['mesa_o_referencia'],
                'notas_generales' => $datos['notas_generales'] ?? null,
                'estado' => 'pendiente', // Estado inicial en el monitor KDS
                'folio' => $folio,
                'enviada_a_cocina_at' => now(),
            ]);

            // ---------------------------------------------------------
            // 2. REGISTRO DE ÍTEMS Y ASIGNACIÓN DE ESTACIÓN KDS
            // ---------------------------------------------------------
            foreach ($datos['items'] as $itemData) {
                $productoId = (int) ($itemData['producto_id'] ?? 0);
                $cantidad = (float) ($itemData['cantidad'] ?? 0);

                if ($cantidad <= 0) {
                    throw new InvalidArgumentException("La cantidad para el producto ID {$productoId} debe ser mayor a cero.");
                }

                /** @var Producto|null $producto */
                $producto = Producto::query()
                    ->with(['categoria', 'receta'])
                    ->where('empresa_id', $empresaId)
                    ->find($productoId);

                if ($producto === null) {
                    throw new InvalidArgumentException("El producto con ID {$productoId} no existe o no pertenece a la empresa.");
                }

                // Determinar estación KDS priorizando:
                // 1) Estación definida en la ficha técnica (receta) del platillo
                // 2) Estación configurada en la categoría del producto
                // 3) Respaldo predeterminado: 'cocina'
                $estacionCocina = $this->resolverEstacionCocina($producto);

                // Consolidar notas especiales y extras/modificadores legibles para cocina/KDS
                $notasEspeciales = $this->formatearNotasConExtras(
                    $itemData['notas_especiales'] ?? null,
                    $itemData['extras'] ?? null
                );

                ComandaItem::query()->create([
                    'comanda_id' => $comanda->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $producto->precio_base,
                    'notas_especiales' => $notasEspeciales,
                    'estado' => 'pendiente', // Estado inicial por ítem en KDS
                    'estacion_cocina' => $estacionCocina,
                ]);
            }

            // Devolver la comanda con relaciones listas para respuesta o vista KDS
            return $comanda->load(['items.producto', 'mesero']);
        });
    }

    /**
     * Determina la estación de preparación (cocina, parrilla, barra, postres)
     * siguiendo la jerarquía de receta, categoría y respaldo por defecto.
     */
    protected function resolverEstacionCocina(Producto $producto): string
    {
        if ($producto->receta && ! empty($producto->receta->estacion_cocina)) {
            return (string) $producto->receta->estacion_cocina;
        }

        if ($producto->categoria && ! empty($producto->categoria->estacion_cocina)) {
            return (string) $producto->categoria->estacion_cocina;
        }

        return 'cocina';
    }

    /**
     * Combina las notas especiales del ítem con los extras/modificadores (si se envían),
     * evitando guardar cadenas vacías.
     *
     * @param string|null       $notas
     * @param array|string|null $extras
     */
    protected function formatearNotasConExtras(?string $notas, array|string|null $extras): ?string
    {
        $partes = [];

        $notaLimpia = trim((string) ($notas ?? ''));
        if ($notaLimpia !== '') {
            $partes[] = $notaLimpia;
        }

        if (is_array($extras) && $extras !== []) {
            $extrasLimpos = array_values(array_filter(array_map(
                static fn ($e): string => trim((string) $e),
                $extras
            )));
            if ($extrasLimpos !== []) {
                $partes[] = 'Extras: ' . implode(', ', $extrasLimpos);
            }
        } elseif (is_string($extras) && trim($extras) !== '') {
            $partes[] = 'Extras: ' . trim($extras);
        }

        return $partes !== [] ? implode(' | ', $partes) : null;
    }

    /**
     * Genera un folio único y legible para la comanda basado en empresa, fecha y consecutivo diario.
     */
    protected function generarFolioUnico(int $empresaId): string
    {
        $fecha = date('Ymd');
        $consecutivo = Comanda::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('created_at', today())
            ->count() + 1;

        return sprintf('CMD-REST-%02d-%s-%03d', $empresaId, $fecha, $consecutivo);
    }
}
