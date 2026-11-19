<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Producto;
use App\Models\Empresa;

class ExtrasCafeteriaSeeder extends Seeder
{
    public function run(): void
    {
        $extras = [
            ['sku' => 'EXT-LECH-ALM', 'nombre' => 'Leche de Almendra', 'precio' => 18.00],
            ['sku' => 'EXT-LECH-DES', 'nombre' => 'Leche Deslactosada', 'precio' => 12.00],
            ['sku' => 'EXT-LECH-AVE', 'nombre' => 'Leche de Avena', 'precio' => 20.00],
            ['sku' => 'EXT-SAB-VAI', 'nombre' => 'Saborizante Vainilla', 'precio' => 15.00],
            ['sku' => 'EXT-SAB-CAR', 'nombre' => 'Saborizante Caramelo', 'precio' => 15.00],
            ['sku' => 'EXT-ESP-EXT', 'nombre' => 'Extra Shot Espresso', 'precio' => 25.00],
        ];

        // Obtenemos todas las empresas registradas
        $empresas = Empresa::all();

        if ($empresas->isEmpty()) {
            // Si no hay tabla empresa o está vacía, usamos empresa_id = 1 por defecto
            $empresas = collect([ (object)['id' => 1] ]);
        }

        foreach ($empresas as $empresa) {
            foreach ($extras as $item) {
                Producto::updateOrCreate(
                    [
                        'sku' => $item['sku'] . '-' . $empresa->id, // SKU único por empresa para evitar conflictos
                        'empresa_id' => $empresa->id
                    ],
                    [
                        'nombre' => $item['nombre'],
                        'precio_base' => $item['precio'],
                        'precio_extra' => $item['precio'],
                        'es_extra' => true,
                        'activo' => true,
                        'categoria_producto_id' => 8 // Categoría de barra
                    ]
                );
            }
        }
    }
}
