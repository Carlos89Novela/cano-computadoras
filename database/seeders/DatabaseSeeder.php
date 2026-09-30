<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Sembrador Principal de la Base de Datos.
 *
 * Coordina la ejecución de los sembradores esenciales del sistema durante
 * la inicialización de entornos locales, pruebas automatizadas o puesta en marcha:
 * - Omite la emisión de eventos de modelo (`WithoutModelEvents`) para optimizar rendimiento de siembra.
 * - Crea el usuario de prueba base para validaciones de desarrollo.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Ejecuta las rutinas de siembra de datos de la aplicación.
     */
    public function run(): void
    {
        // Genera un usuario de pruebas predeterminado para el entorno de desarrollo
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}

