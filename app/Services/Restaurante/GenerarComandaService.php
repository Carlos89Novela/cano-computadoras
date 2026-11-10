<?php

namespace App\Services\Restaurante;

use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Sucursal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GenerarComandaService
{
    /**
     * @param array{
     *     empresa_id: int,
     *     sucursal_id: int,
     *     venta_id?: int|null,
     *     mesero_user_id?: int|null,
     *     mesa_o_referencia: string,
     *     notas_generales?: string|null,
     *     items: array<int, array{
     *         producto_id: int,
     *         cantidad: float|int|numeric-string,
     *         notas_especiales?: string|null
     *     }>
     * } $datos
     */
    public function ejecutar(array $datos): Comanda
    {
        return DB::transaction(function () use ($datos) {
            /** @var Empresa $empresa */
            $empresa = Empresa::query()->findOrFail($datos['empresa_id']);
            /** @var Sucursal $sucursal */ $sucursal = Sucursal::query()->where('empresa_id', $empresa->id)->findOrFail($datos['sucursal_id']);

            if (empty($datos['items'])) {
                throw new InvalidArgumentException('La comanda debe incluir al menos un producto.');
            }

            $folio = $this->generarFolio($sucursal);

            /** @var Comanda $comanda */ $comanda = Comanda::query()->create([
                            'empresa_id' => $empresa->id,
                            'sucursal_id' => $sucursal->id,
                            'venta_id' => $datos['venta_id'] ?? null,
                            'mesero_user_id' => $datos['mesero_user_id'] ?? null,
                            'folio' => $folio,
                            'mesa_o_referencia' => $datos['mesa_o_referencia'],
                            'estado' => 'pendiente',
                            'notas_generales' => $datos['notas_generales'] ?? null,
                            'enviada_a_cocina_at' => now(),
                        ]);

            foreach ($datos['items'] as $itemData) {
                /** @var Producto $producto */ $producto = Producto::query()
                    ->where('empresa_id', $empresa->id)
                    ->with('receta')
                    ->findOrFail($itemData['producto_id']);

                $receta = $producto->receta;
                $estacion = $receta !== null ? $receta->estacion_cocina : 'cocina';

                ComandaItem::query()->create([
                    'comanda_id' => $comanda->id,
                    'producto_id' => $producto->id,
                    'cantidad' => (float) $itemData['cantidad'],
                    'estacion_cocina' => $estacion,
                    'estado' => 'pendiente',
                    'notas_especiales' => $itemData['notas_especiales'] ?? null,
                ]);
            }

            return $comanda->load(['items.producto', 'mesero']);
        });
    }

    protected function generarFolio(Sucursal $sucursal): string
    {
        $fecha = Carbon::now()->format('Ymd');
        $codigoSucursal = strtoupper((string) ($sucursal->codigo ?? 'SUC'));
        $prefijo = "CMD-{$codigoSucursal}-{$fecha}";

        $ultimoFolio = Comanda::query()
            ->where('sucursal_id', $sucursal->id)
            ->where('folio', 'like', "{$prefijo}-%")
            ->orderByDesc('id')
            ->value('folio');

        $consecutivo = 1;
        if ($ultimoFolio !== null) {
            $partes = explode('-', (string) $ultimoFolio);
            $consecutivo = ((int) end($partes)) + 1;
        }

        return sprintf('%s-%03d', $prefijo, $consecutivo);
    }
}
