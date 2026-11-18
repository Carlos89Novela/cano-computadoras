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

class GenerarComandaService
{
    /**
     * Ejecuta la lógica transaccional para crear una comanda y sus ítems,
     * asignando de forma inteligente la estación de preparación correspondiente.
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
     *         notas_especiales?: string|null
     *     }>
     * } $datos
     */
    public function ejecutar(array $datos): Comanda
    {
        // ---------------------------------------------------------
        // 1. TRANSACCIÓN ATÓMICA DE BASE DE DATOS
        // Garantiza que si un ítem falla, toda la comanda hace rollback.
        // ---------------------------------------------------------
        return DB::transaction(function () use ($datos): Comanda {

            // Generar un folio único secuencial para el ticket y KDS
            $folio = $this->generarFolioUnico((int) $datos['empresa_id']);

            /** @var Comanda $comanda */
            $comanda = Comanda::query()->create([
                'empresa_id' => $datos['empresa_id'],
                'sucursal_id' => $datos['sucursal_id'],
                'mesero_user_id' => $datos['mesero_user_id'],
                'mesa_o_referencia' => $datos['mesa_o_referencia'],
                'notas_generales' => $datos['notas_generales'] ?? null,
                'estado' => 'pendiente', // Estado inicial en KDS
                'folio' => $folio,
                'enviada_a_cocina_at' => now(),
            ]);

            // ---------------------------------------------------------
            // 2. PROCESAMIENTO Y REGISTRO DE ÍTEMS DE LA COMANDA
            // ---------------------------------------------------------
            foreach ($datos['items'] as $itemData) {
                /** @var Producto|null $producto */
                $producto = Producto::query()
                    ->with(['categoria', 'receta'])
                    ->find($itemData['producto_id']);

                if ($producto === null) {
                    throw new InvalidArgumentException("El producto con ID {$itemData['producto_id']} no existe o está inactivo.");
                }

                // Jerarquía limpia y directa para determinar la estación KDS:
                // 1. Estación de la receta técnica.
                // 2. Estación de la categoría del producto.
                // 3. Respaldo por defecto: 'cocina'.
                $estacionCocina = 'cocina';
                if ($producto->receta && $producto->receta->estacion_cocina) {
                    $estacionCocina = $producto->receta->estacion_cocina;
                } elseif ($producto->categoria && $producto->categoria->estacion_cocina) {
                    $estacionCocina = $producto->categoria->estacion_cocina;
                }

                // Registrar el detalle del platillo o bebida en la comanda
                ComandaItem::query()->create([
                    'comanda_id' => $comanda->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $itemData['cantidad'],
                    'precio_unitario' => $producto->precio_base,
                    'notas_especiales' => $itemData['notas_especiales'] ?? null,
                    'estado' => 'pendiente', // Estado inicial individual por ítem
                    'estacion_cocina' => $estacionCocina, // cocina, postres, parrilla, barra
                ]);
            }

            // Retornar la comanda cargando sus relaciones principales
            return $comanda->load(['items.producto', 'mesero']);
        });
    }

    /**
     * Genera un folio único y legible para la comanda basado en la fecha y la empresa.
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
