<?php

namespace Database\Seeders;

use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\ProductoReceta;
use App\Models\ProductoRecetaDetalle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RestauranteMenuCompletoSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::where('giro_comercial', 'restaurantes_alimentos')
            ->orWhere('nombre', 'like', '%Fogata%')
            ->first();

        if (! $empresa) {
            $this->command->error('No se encontró la empresa de restaurante.');
            return;
        }

        DB::transaction(function () use ($empresa) {
            // =========================================================================
            // 1. CATEGORÍAS
            // =========================================================================
            $catInsumosCocina = CategoriaProducto::firstOrCreate(
                ['empresa_id' => $empresa->id, 'nombre' => 'Insumos de Cocina'],
                ['activo' => true, 'descripcion' => 'Materia prima cárnica, lácteos y vegetales']
            );

            $catInsumosBarra = CategoriaProducto::firstOrCreate(
                ['empresa_id' => $empresa->id, 'nombre' => 'Insumos de Barra'],
                ['activo' => true, 'descripcion' => 'Licores, jarabes, café y destilados']
            );

            $catEntradas = CategoriaProducto::firstOrCreate(
                ['empresa_id' => $empresa->id, 'nombre' => 'Entradas & Botanas'],
                ['activo' => true, 'descripcion' => 'Aperitivos y entradas']
            );

            $catSopas = CategoriaProducto::firstOrCreate(
                ['empresa_id' => $empresa->id, 'nombre' => 'Sopas & Cremas'],
                ['activo' => true, 'descripcion' => 'Caldos, cremas y sopas del día']
            );

            $catFuertes = CategoriaProducto::firstOrCreate(
                ['empresa_id' => $empresa->id, 'nombre' => 'Platos Fuertes'],
                ['activo' => true, 'descripcion' => 'Cortes, pastas, aves y especialidades']
            );

            $catPostres = CategoriaProducto::firstOrCreate(
                ['empresa_id' => $empresa->id, 'nombre' => 'Postres de Autor'],
                ['activo' => true, 'descripcion' => 'Repostería fina y postres']
            );

            $catBebidas = CategoriaProducto::firstOrCreate(
                ['empresa_id' => $empresa->id, 'nombre' => 'Bebidas & Coctelería'],
                ['activo' => true, 'descripcion' => 'Cafetería, tés, mocktails y coctelería']
            );

            // =========================================================================
            // 2. INSUMOS / MATERIAS PRIMAS (Con Costos de Referencia Unitarios)
            // =========================================================================
            $insumosData = [
                // Cárnicos y Proteínas
                ['sku' => 'INS-RIBEYE', 'nombre' => 'Rib Eye Calidad Choice (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 380.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-ARRACHERA', 'nombre' => 'Arrachera Marinada (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 260.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-PECHUGA', 'nombre' => 'Pechuga de Pollo Fresca (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 110.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-CAMARON', 'nombre' => 'Camarón U15 Limpio (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 290.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-SALMON', 'nombre' => 'Filete de Salmón Chileno (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 360.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-TOCINO', 'nombre' => 'Tocino Ahumado de Cerdo (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 140.00, 'cat' => $catInsumosCocina->id],

                // Lácteos y Quesos
                ['sku' => 'INS-MOZZARELLA', 'nombre' => 'Queso Mozzarella en Barra (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 150.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-PARMESANO', 'nombre' => 'Queso Parmesano Reggiano (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 420.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-CREMA-ACIDA', 'nombre' => 'Crema Ácida Entera (Litro)', 'unidad_medida' => 'litro', 'costo_referencia' => 45.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-MANTEQUILLA', 'nombre' => 'Mantequilla sin Sal (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 160.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-LECHE-ENTERA', 'nombre' => 'Leche Entera Pasteurizada (Litro)', 'unidad_medida' => 'litro', 'costo_referencia' => 26.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-Q-PHILADELPHIA', 'nombre' => 'Queso Crema Tipo Filadelfia (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 135.00, 'cat' => $catInsumosCocina->id],

                // Vegetales, Frutas y Abarrotes
                ['sku' => 'INS-AGUACATE', 'nombre' => 'Aguacate Hass de Primera (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 75.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-JITOMATE', 'nombre' => 'Jitomate Saladette (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 28.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-CEBOLLA-B', 'nombre' => 'Cebolla Blanca (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 22.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-CEBOLLA-M', 'nombre' => 'Cebolla Morada (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 30.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-CHAMPIÑON', 'nombre' => 'Champiñón Blanco Fresco (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 85.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-PAPA', 'nombre' => 'Papa Russet para Freír (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 32.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-LECHUGA-OREJ', 'nombre' => 'Lechuga Orejona Romana (Pza)', 'unidad_medida' => 'pza', 'costo_referencia' => 18.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-LIMON', 'nombre' => 'Limón Colima sin Semilla (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 35.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-AJO', 'nombre' => 'Ajo Morado Dientes (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 90.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-CALDO-RES', 'nombre' => 'Fondo Oscuro de Res (Litro)', 'unidad_medida' => 'litro', 'costo_referencia' => 35.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-CALDO-POLLO', 'nombre' => 'Fondo Claro de Ave (Litro)', 'unidad_medida' => 'litro', 'costo_referencia' => 25.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-PASTA-FETT', 'nombre' => 'Pasta Fettuccine al Huevo (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 60.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-ACEITE-OLIVA', 'nombre' => 'Aceite de Oliva Extra Virgen (Litro)', 'unidad_medida' => 'litro', 'costo_referencia' => 190.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-TORTILLA-MAIZ', 'nombre' => 'Tortilla de Maíz Taquera (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 24.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-PAN-BAGUETTE', 'nombre' => 'Pan Baguette Artesanal (Pza)', 'unidad_medida' => 'pza', 'costo_referencia' => 22.00, 'cat' => $catInsumosCocina->id],

                // Postres y Dulcería
                ['sku' => 'INS-CHOCOLATE-70', 'nombre' => 'Chocolate Amargo 70% Callebaut (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 280.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-AZUCAR-STD', 'nombre' => 'Azúcar Estándar Refinada (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 30.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-HARINA-TRIGO', 'nombre' => 'Harina de Trigo Extra Fina (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 22.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-HUEVO-FRESCO', 'nombre' => 'Huevo Blanco de Granja (Pza)', 'unidad_medida' => 'pza', 'costo_referencia' => 3.50, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-VAINILLA-EXT', 'nombre' => 'Extracto Natural de Vainilla Papantla (Litro)', 'unidad_medida' => 'litro', 'costo_referencia' => 240.00, 'cat' => $catInsumosCocina->id],
                ['sku' => 'INS-DULCE-LECHE', 'nombre' => 'Cajeta / Dulce de Leche Artesanal (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 95.00, 'cat' => $catInsumosCocina->id],

                // Insumos de Barra, Cafetería y Licores
                ['sku' => 'INS-CAFE-GRANO', 'nombre' => 'Café de Altura Chiapas en Grano (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 260.00, 'cat' => $catInsumosBarra->id],
                ['sku' => 'INS-TE-VERDE', 'nombre' => 'Té Verde Sencha Orgánico a Granel (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 350.00, 'cat' => $catInsumosBarra->id],
                ['sku' => 'INS-TE-NEGRO', 'nombre' => 'Té Negro English Breakfast (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 320.00, 'cat' => $catInsumosBarra->id],
                ['sku' => 'INS-TEQUILA-BL', 'nombre' => 'Tequila Reposado 100% Agave 750ml (Botella)', 'unidad_medida' => 'pza', 'costo_referencia' => 340.00, 'cat' => $catInsumosBarra->id],
                ['sku' => 'INS-MEZCAL-ESP', 'nombre' => 'Mezcal Espadín Artesanal Oaxaca 750ml (Botella)', 'unidad_medida' => 'pza', 'costo_referencia' => 450.00, 'cat' => $catInsumosBarra->id],
                ['sku' => 'INS-RON-BLANCO', 'nombre' => 'Ron Blanco Añejo Carta Blanca 750ml (Botella)', 'unidad_medida' => 'pza', 'costo_referencia' => 210.00, 'cat' => $catInsumosBarra->id],
                ['sku' => 'INS-GINEBRA-LON', 'nombre' => 'Ginebra London Dry Gin 750ml (Botella)', 'unidad_medida' => 'pza', 'costo_referencia' => 410.00, 'cat' => $catInsumosBarra->id],
                ['sku' => 'INS-VINO-TINTO', 'nombre' => 'Vino Tinto Cabernet Sauvignon Casa Madero 750ml', 'unidad_medida' => 'pza', 'costo_referencia' => 320.00, 'cat' => $catInsumosBarra->id],
                ['sku' => 'INS-HIERBABUENA', 'nombre' => 'Hierbabuena Fresca en Ramillete (Kg)', 'unidad_medida' => 'kg', 'costo_referencia' => 60.00, 'cat' => $catInsumosBarra->id],
                ['sku' => 'INS-AGUA-QUINA', 'nombre' => 'Agua Tónica Premium 200ml (Pza)', 'unidad_medida' => 'pza', 'costo_referencia' => 18.00, 'cat' => $catInsumosBarra->id],
                ['sku' => 'INS-AGUA-MINERAL', 'nombre' => 'Agua Mineral de Manantial 355ml (Pza)', 'unidad_medida' => 'pza', 'costo_referencia' => 14.00, 'cat' => $catInsumosBarra->id],
                ['sku' => 'INS-JARABE-NAT', 'nombre' => 'Jarabe Natural de Azúcar de Caña (Litro)', 'unidad_medida' => 'litro', 'costo_referencia' => 40.00, 'cat' => $catInsumosBarra->id],
            ];

            $insumosMap = [];
            foreach ($insumosData as $ins) {
                $p = Producto::firstOrCreate(
                    ['empresa_id' => $empresa->id, 'sku' => $ins['sku']],
                    [
                        'categoria_producto_id' => $ins['cat'],
                        'nombre' => $ins['nombre'],
                        'unidad_medida' => $ins['unidad_medida'],
                        'costo_referencia' => $ins['costo_referencia'],
                        'precio_base' => $ins['costo_referencia'], // Precio base referencial
                        'es_receta' => false,
                        'activo' => true,
                    ]
                );
                $insumosMap[$ins['sku']] = $p;
            }

            // =========================================================================
            // 3. RECETAS DE COMIDA (20 PLATILLOS: Sopas, Entradas, Fuertes y Postres)
            // =========================================================================
            $platillosComida = [
                // --- ENTRADAS (5) ---
                [
                    'sku' => 'ENT-GUACAMOLE', 'nombre' => 'Guacamole Fogata con Totopos', 'cat' => $catEntradas->id, 'precio' => 145.00,
                    'estacion' => 'cocina', 'tiempo' => 6,
                    'items' => [
                        ['sku' => 'INS-AGUACATE', 'cant' => 0.250, 'merma' => 10],
                        ['sku' => 'INS-JITOMATE', 'cant' => 0.080, 'merma' => 5],
                        ['sku' => 'INS-CEBOLLA-B', 'cant' => 0.050, 'merma' => 5],
                        ['sku' => 'INS-LIMON', 'cant' => 0.040, 'merma' => 5],
                        ['sku' => 'INS-TORTILLA-MAIZ', 'cant' => 0.120, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'ENT-QUESO-FUND', 'nombre' => 'Queso Fundido con Chistorra y Champiñón', 'cat' => $catEntradas->id, 'precio' => 175.00,
                    'estacion' => 'cocina', 'tiempo' => 8,
                    'items' => [
                        ['sku' => 'INS-MOZZARELLA', 'cant' => 0.200, 'merma' => 2],
                        ['sku' => 'INS-CHAMPIÑON', 'cant' => 0.080, 'merma' => 5],
                        ['sku' => 'INS-TORTILLA-MAIZ', 'cant' => 0.100, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'ENT-CARPACCIO-SALM', 'nombre' => 'Carpaccio de Salmón al Eneldo', 'cat' => $catEntradas->id, 'precio' => 220.00,
                    'estacion' => 'cocina', 'tiempo' => 8,
                    'items' => [
                        ['sku' => 'INS-SALMON', 'cant' => 0.120, 'merma' => 4],
                        ['sku' => 'INS-ACEITE-OLIVA', 'cant' => 0.025, 'merma' => 0],
                        ['sku' => 'INS-PARMESANO', 'cant' => 0.030, 'merma' => 0],
                        ['sku' => 'INS-LIMON', 'cant' => 0.030, 'merma' => 5],
                    ],
                ],
                [
                    'sku' => 'ENT-PAPAS-TRUFA', 'nombre' => 'Papas Rústicas con Parmesano', 'cat' => $catEntradas->id, 'precio' => 130.00,
                    'estacion' => 'cocina', 'tiempo' => 10,
                    'items' => [
                        ['sku' => 'INS-PAPA', 'cant' => 0.350, 'merma' => 8],
                        ['sku' => 'INS-PARMESANO', 'cant' => 0.040, 'merma' => 0],
                        ['sku' => 'INS-ACEITE-OLIVA', 'cant' => 0.020, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'ENT-ENSALADA-CESAR', 'nombre' => 'Ensalada César con Pollo a la Parrilla', 'cat' => $catEntradas->id, 'precio' => 165.00,
                    'estacion' => 'cocina', 'tiempo' => 10,
                    'items' => [
                        ['sku' => 'INS-LECHUGA-OREJ', 'cant' => 1.000, 'merma' => 10],
                        ['sku' => 'INS-PECHUGA', 'cant' => 0.150, 'merma' => 5],
                        ['sku' => 'INS-PARMESANO', 'cant' => 0.035, 'merma' => 0],
                        ['sku' => 'INS-PAN-BAGUETTE', 'cant' => 0.250, 'merma' => 0],
                    ],
                ],

                // --- SOPAS & CREMAS (4) ---
                [
                    'sku' => 'SOP-CEBOLLA-FRAN', 'nombre' => 'Sopa de Cebolla Francesa Gratinada', 'cat' => $catSopas->id, 'precio' => 150.00,
                    'estacion' => 'cocina', 'tiempo' => 12,
                    'items' => [
                        ['sku' => 'INS-CEBOLLA-B', 'cant' => 0.300, 'merma' => 8],
                        ['sku' => 'INS-CALDO-RES', 'cant' => 0.300, 'merma' => 0],
                        ['sku' => 'INS-MOZZARELLA', 'cant' => 0.080, 'merma' => 0],
                        ['sku' => 'INS-PAN-BAGUETTE', 'cant' => 0.300, 'merma' => 0],
                        ['sku' => 'INS-MANTEQUILLA', 'cant' => 0.020, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'SOP-CREMA-CHAMP', 'nombre' => 'Crema Silvestre de Champiñones Portobello', 'cat' => $catSopas->id, 'precio' => 140.00,
                    'estacion' => 'cocina', 'tiempo' => 10,
                    'items' => [
                        ['sku' => 'INS-CHAMPIÑON', 'cant' => 0.200, 'merma' => 5],
                        ['sku' => 'INS-CREMA-ACIDA', 'cant' => 0.100, 'merma' => 0],
                        ['sku' => 'INS-CALDO-POLLO', 'cant' => 0.200, 'merma' => 0],
                        ['sku' => 'INS-MANTEQUILLA', 'cant' => 0.025, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'SOP-TORTILLA-TRAD', 'nombre' => 'Sopa de Tortilla Tradicional Azteca', 'cat' => $catSopas->id, 'precio' => 135.00,
                    'estacion' => 'cocina', 'tiempo' => 8,
                    'items' => [
                        ['sku' => 'INS-TORTILLA-MAIZ', 'cant' => 0.150, 'merma' => 0],
                        ['sku' => 'INS-JITOMATE', 'cant' => 0.120, 'merma' => 4],
                        ['sku' => 'INS-CALDO-POLLO', 'cant' => 0.250, 'merma' => 0],
                        ['sku' => 'INS-AGUACATE', 'cant' => 0.060, 'merma' => 10],
                        ['sku' => 'INS-CREMA-ACIDA', 'cant' => 0.040, 'merma' => 0],
                        ['sku' => 'INS-MOZZARELLA', 'cant' => 0.040, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'SOP-CREMA-JITOM', 'nombre' => 'Bisque Rústico de Jitomate Rostizado', 'cat' => $catSopas->id, 'precio' => 130.00,
                    'estacion' => 'cocina', 'tiempo' => 9,
                    'items' => [
                        ['sku' => 'INS-JITOMATE', 'cant' => 0.280, 'merma' => 5],
                        ['sku' => 'INS-AJO', 'cant' => 0.015, 'merma' => 5],
                        ['sku' => 'INS-ACEITE-OLIVA', 'cant' => 0.020, 'merma' => 0],
                        ['sku' => 'INS-CREMA-ACIDA', 'cant' => 0.080, 'merma' => 0],
                    ],
                ],

                // --- PLATOS FUERTES (7) ---
                [
                    'sku' => 'PF-RIBEYE-400G', 'nombre' => 'Rib Eye a las Brasas (400g) con Papas', 'cat' => $catFuertes->id, 'precio' => 490.00,
                    'estacion' => 'parrilla', 'tiempo' => 18,
                    'items' => [
                        ['sku' => 'INS-RIBEYE', 'cant' => 0.400, 'merma' => 8],
                        ['sku' => 'INS-PAPA', 'cant' => 0.200, 'merma' => 5],
                        ['sku' => 'INS-MANTEQUILLA', 'cant' => 0.030, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'PF-ARRACHERA-BRA', 'nombre' => 'Arrachera Norteña Fogata (300g)', 'cat' => $catFuertes->id, 'precio' => 340.00,
                    'estacion' => 'parrilla', 'tiempo' => 15,
                    'items' => [
                        ['sku' => 'INS-ARRACHERA', 'cant' => 0.300, 'merma' => 6],
                        ['sku' => 'INS-AGUACATE', 'cant' => 0.100, 'merma' => 10],
                        ['sku' => 'INS-CEBOLLA-B', 'cant' => 0.100, 'merma' => 5],
                        ['sku' => 'INS-TORTILLA-MAIZ', 'cant' => 0.120, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'PF-SALMON-GLAZED', 'nombre' => 'Salmón a la Plancha con Mantequilla de Ajo', 'cat' => $catFuertes->id, 'precio' => 380.00,
                    'estacion' => 'parrilla', 'tiempo' => 14,
                    'items' => [
                        ['sku' => 'INS-SALMON', 'cant' => 0.220, 'merma' => 4],
                        ['sku' => 'INS-MANTEQUILLA', 'cant' => 0.040, 'merma' => 0],
                        ['sku' => 'INS-AJO', 'cant' => 0.015, 'merma' => 5],
                        ['sku' => 'INS-LIMON', 'cant' => 0.030, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'PF-CAMARONES-MOJO', 'nombre' => 'Camarones Jumbo al Mojo de Ajo con Arroz', 'cat' => $catFuertes->id, 'precio' => 320.00,
                    'estacion' => 'cocina', 'tiempo' => 12,
                    'items' => [
                        ['sku' => 'INS-CAMARON', 'cant' => 0.220, 'merma' => 5],
                        ['sku' => 'INS-AJO', 'cant' => 0.030, 'merma' => 5],
                        ['sku' => 'INS-MANTEQUILLA', 'cant' => 0.040, 'merma' => 0],
                        ['sku' => 'INS-ACEITE-OLIVA', 'cant' => 0.020, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'PF-FETTUCCINE-ALF', 'nombre' => 'Fettuccine Alfredo con Pechuga y Tocino', 'cat' => $catFuertes->id, 'precio' => 245.00,
                    'estacion' => 'cocina', 'tiempo' => 14,
                    'items' => [
                        ['sku' => 'INS-PASTA-FETT', 'cant' => 0.180, 'merma' => 0],
                        ['sku' => 'INS-PECHUGA', 'cant' => 0.140, 'merma' => 4],
                        ['sku' => 'INS-TOCINO', 'cant' => 0.050, 'merma' => 5],
                        ['sku' => 'INS-CREMA-ACIDA', 'cant' => 0.100, 'merma' => 0],
                        ['sku' => 'INS-PARMESANO', 'cant' => 0.040, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'PF-POLLO-PARMES', 'nombre' => 'Suprema de Pollo Gratinada con Mozzarella', 'cat' => $catFuertes->id, 'precio' => 235.00,
                    'estacion' => 'cocina', 'tiempo' => 15,
                    'items' => [
                        ['sku' => 'INS-PECHUGA', 'cant' => 0.220, 'merma' => 4],
                        ['sku' => 'INS-MOZZARELLA', 'cant' => 0.080, 'merma' => 0],
                        ['sku' => 'INS-JITOMATE', 'cant' => 0.100, 'merma' => 5],
                        ['sku' => 'INS-PARMESANO', 'cant' => 0.020, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'PF-TACOS-RIBEYE', 'nombre' => 'Trilogía de Tacos de Rib Eye en Maíz', 'cat' => $catFuertes->id, 'precio' => 210.00,
                    'estacion' => 'parrilla', 'tiempo' => 10,
                    'items' => [
                        ['sku' => 'INS-RIBEYE', 'cant' => 0.180, 'merma' => 5],
                        ['sku' => 'INS-TORTILLA-MAIZ', 'cant' => 0.090, 'merma' => 0],
                        ['sku' => 'INS-AGUACATE', 'cant' => 0.060, 'merma' => 10],
                        ['sku' => 'INS-CEBOLLA-M', 'cant' => 0.030, 'merma' => 5],
                    ],
                ],

                // --- POSTRES (4) ---
                [
                    'sku' => 'POS-VOLCAN-CHOC', 'nombre' => 'Volcán de Chocolate Belga Caliente', 'cat' => $catPostres->id, 'precio' => 145.00,
                    'estacion' => 'postres', 'tiempo' => 12,
                    'items' => [
                        ['sku' => 'INS-CHOCOLATE-70', 'cant' => 0.080, 'merma' => 2],
                        ['sku' => 'INS-MANTEQUILLA', 'cant' => 0.040, 'merma' => 0],
                        ['sku' => 'INS-HUEVO-FRESCO', 'cant' => 1.000, 'merma' => 0],
                        ['sku' => 'INS-AZUCAR-STD', 'cant' => 0.035, 'merma' => 0],
                        ['sku' => 'INS-HARINA-TRIGO', 'cant' => 0.025, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'POS-CHEESECAKE', 'nombre' => 'Cheesecake Estilo New York Clásico', 'cat' => $catPostres->id, 'precio' => 135.00,
                    'estacion' => 'postres', 'tiempo' => 5,
                    'items' => [
                        ['sku' => 'INS-Q-PHILADELPHIA', 'cant' => 0.120, 'merma' => 2],
                        ['sku' => 'INS-AZUCAR-STD', 'cant' => 0.040, 'merma' => 0],
                        ['sku' => 'INS-CREMA-ACIDA', 'cant' => 0.030, 'merma' => 0],
                        ['sku' => 'INS-VAINILLA-EXT', 'cant' => 0.005, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'POS-CREPAS-CAJETA', 'nombre' => 'Crepas Flameadas con Cajeta y Nuez', 'cat' => $catPostres->id, 'precio' => 130.00,
                    'estacion' => 'postres', 'tiempo' => 8,
                    'items' => [
                        ['sku' => 'INS-LECHE-ENTERA', 'cant' => 0.120, 'merma' => 0],
                        ['sku' => 'INS-HARINA-TRIGO', 'cant' => 0.060, 'merma' => 0],
                        ['sku' => 'INS-HUEVO-FRESCO', 'cant' => 1.000, 'merma' => 0],
                        ['sku' => 'INS-DULCE-LECHE', 'cant' => 0.070, 'merma' => 2],
                    ],
                ],
                [
                    'sku' => 'POS-PANNA-COTTA', 'nombre' => 'Panna Cotta de Vainilla con Frutos', 'cat' => $catPostres->id, 'precio' => 125.00,
                    'estacion' => 'postres', 'tiempo' => 4,
                    'items' => [
                        ['sku' => 'INS-LECHE-ENTERA', 'cant' => 0.150, 'merma' => 0],
                        ['sku' => 'INS-CREMA-ACIDA', 'cant' => 0.060, 'merma' => 0],
                        ['sku' => 'INS-AZUCAR-STD', 'cant' => 0.030, 'merma' => 0],
                        ['sku' => 'INS-VAINILLA-EXT', 'cant' => 0.008, 'merma' => 0],
                    ],
                ],
            ];

            // =========================================================================
            // 4. RECETAS DE BEBIDAS (10: Cafetería, Tés, Coctelería con Alcohol)
            // =========================================================================
            $platillosBebidas = [
                // --- CAFETERÍA Y TÉS (4) ---
                [
                    'sku' => 'BEB-ESPRESSO-DOB', 'nombre' => 'Espresso Doble de Grano Chiapaneco', 'cat' => $catBebidas->id, 'precio' => 60.00,
                    'estacion' => 'barra', 'tiempo' => 3,
                    'items' => [
                        ['sku' => 'INS-CAFE-GRANO', 'cant' => 0.018, 'merma' => 2],
                    ],
                ],
                [
                    'sku' => 'BEB-CAPPUCCINO', 'nombre' => 'Cappuccino Italiano Cremoso', 'cat' => $catBebidas->id, 'precio' => 75.00,
                    'estacion' => 'barra', 'tiempo' => 4,
                    'items' => [
                        ['sku' => 'INS-CAFE-GRANO', 'cant' => 0.012, 'merma' => 2],
                        ['sku' => 'INS-LECHE-ENTERA', 'cant' => 0.200, 'merma' => 3],
                    ],
                ],
                [
                    'sku' => 'BEB-TE-VERDE-SEN', 'nombre' => 'Té Verde Sencha Caliente en Tetera', 'cat' => $catBebidas->id, 'precio' => 65.00,
                    'estacion' => 'barra', 'tiempo' => 4,
                    'items' => [
                        ['sku' => 'INS-TE-VERDE', 'cant' => 0.006, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'BEB-ICED-BLACK-TEA', 'nombre' => 'Té Negro Helado con Limón y Menta', 'cat' => $catBebidas->id, 'precio' => 70.00,
                    'estacion' => 'barra', 'tiempo' => 4,
                    'items' => [
                        ['sku' => 'INS-TE-NEGRO', 'cant' => 0.008, 'merma' => 0],
                        ['sku' => 'INS-LIMON', 'cant' => 0.030, 'merma' => 5],
                        ['sku' => 'INS-JARABE-NAT', 'cant' => 0.030, 'merma' => 0],
                    ],
                ],

                // --- COCTELERÍA Y BEBIDAS CON ALCOHOL (6) ---
                [
                    'sku' => 'BEB-PALOMA-TEQ', 'nombre' => 'Paloma Clásica de Tequila Reposado', 'cat' => $catBebidas->id, 'precio' => 140.00,
                    'estacion' => 'barra', 'tiempo' => 5,
                    'items' => [
                        ['sku' => 'INS-TEQUILA-BL', 'cant' => 0.060, 'merma' => 2], // 2 oz (60ml de botella)
                        ['sku' => 'INS-LIMON', 'cant' => 0.030, 'merma' => 5],
                        ['sku' => 'INS-AGUA-MINERAL', 'cant' => 0.500, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'BEB-MEZCALITA-ESP', 'nombre' => 'Mezcalita de la Casa con Sal de Gusano', 'cat' => $catBebidas->id, 'precio' => 165.00,
                    'estacion' => 'barra', 'tiempo' => 5,
                    'items' => [
                        ['sku' => 'INS-MEZCAL-ESP', 'cant' => 0.060, 'merma' => 2],
                        ['sku' => 'INS-LIMON', 'cant' => 0.035, 'merma' => 5],
                        ['sku' => 'INS-JARABE-NAT', 'cant' => 0.025, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'BEB-MOJITO-CUB', 'nombre' => 'Mojito Cubano Tradicional con Ron Añejo', 'cat' => $catBebidas->id, 'precio' => 135.00,
                    'estacion' => 'barra', 'tiempo' => 5,
                    'items' => [
                        ['sku' => 'INS-RON-BLANCO', 'cant' => 0.060, 'merma' => 2],
                        ['sku' => 'INS-HIERBABUENA', 'cant' => 0.015, 'merma' => 10],
                        ['sku' => 'INS-LIMON', 'cant' => 0.030, 'merma' => 5],
                        ['sku' => 'INS-JARABE-NAT', 'cant' => 0.030, 'merma' => 0],
                        ['sku' => 'INS-AGUA-MINERAL', 'cant' => 0.500, 'merma' => 0],
                    ],
                ],
                [
                    'sku' => 'BEB-GIN-TONIC', 'nombre' => 'Gin & Tonic Clásico con Botánicos', 'cat' => $catBebidas->id, 'precio' => 155.00,
                    'estacion' => 'barra', 'tiempo' => 4,
                    'items' => [
                        ['sku' => 'INS-GINEBRA-LON', 'cant' => 0.060, 'merma' => 2],
                        ['sku' => 'INS-AGUA-QUINA', 'cant' => 1.000, 'merma' => 0],
                        ['sku' => 'INS-LIMON', 'cant' => 0.020, 'merma' => 5],
                    ],
                ],
                [
                    'sku' => 'BEB-COPA-VINO-T', 'nombre' => 'Copa de Vino Tinto Casa Madero (150ml)', 'cat' => $catBebidas->id, 'precio' => 150.00,
                    'estacion' => 'barra', 'tiempo' => 2,
                    'items' => [
                        ['sku' => 'INS-VINO-TINTO', 'cant' => 0.200, 'merma' => 2], // 150ml de botella de 750ml = 0.20 botella
                    ],
                ],
                [
                    'sku' => 'BEB-CARAJILLO-AG', 'nombre' => 'Carajillo Shakerato con Licor y Espresso', 'cat' => $catBebidas->id, 'precio' => 160.00,
                    'estacion' => 'barra', 'tiempo' => 4,
                    'items' => [
                        ['sku' => 'INS-CAFE-GRANO', 'cant' => 0.015, 'merma' => 2],
                        ['sku' => 'INS-RON-BLANCO', 'cant' => 0.050, 'merma' => 2],
                    ],
                ],
            ];

            // =========================================================================
            // 5. INSERCIÓN MASIVA DE RECETAS Y DETALLES (BOM)
            // =========================================================================
            $todosLosPlatillos = array_merge($platillosComida, $platillosBebidas);

            foreach ($todosLosPlatillos as $pData) {
                // A. Crear o Actualizar Producto Platillo
                $platillo = Producto::updateOrCreate(
                    ['empresa_id' => $empresa->id, 'sku' => $pData['sku']],
                    [
                        'categoria_producto_id' => $pData['cat'],
                        'nombre' => $pData['nombre'],
                        'unidad_medida' => 'pza',
                        'precio_base' => $pData['precio'],
                        'es_receta' => true,
                        'activo' => true,
                    ]
                );

                // B. Crear Encabezado de Receta (Ficha Técnica)
                $receta = ProductoReceta::updateOrCreate(
                    ['empresa_id' => $empresa->id, 'producto_id' => $platillo->id],
                    [
                        'rendimiento_porciones' => 1.00,
                        'tiempo_preparacion_minutos' => $pData['tiempo'],
                        'estacion_cocina' => $pData['estacion'],
                        'instrucciones' => 'Preparación estándar según ficha técnica de cocina/barra.',
                        'activo' => true,
                    ]
                );

                // C. Vincular Insumos (BOM)
                ProductoRecetaDetalle::where('producto_receta_id', $receta->id)->delete();

                $costoAcumulado = 0.0;

                foreach ($pData['items'] as $item) {
                    if (isset($insumosMap[$item['sku']])) {
                        $insumoModel = $insumosMap[$item['sku']];
                        $cantidad = (float) $item['cant'];
                        $merma = (float) $item['merma'];

                        ProductoRecetaDetalle::create([
                            'producto_receta_id' => $receta->id,
                            'insumo_producto_id' => $insumoModel->id,
                            'cantidad_requerida' => $cantidad,
                            'porcentaje_merma' => $merma,
                        ]);

                        $costoConMerma = ($insumoModel->costo_referencia * $cantidad) * (1 + ($merma / 100));
                        $costoAcumulado += $costoConMerma;
                    }
                }

                // Actualizar costo de referencia teórico del platillo terminado
                $platillo->update(['costo_referencia' => round($costoAcumulado, 2)]);
            }
        });
    }
}
