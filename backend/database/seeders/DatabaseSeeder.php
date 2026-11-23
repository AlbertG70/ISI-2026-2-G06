<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DatabaseSeeder
 *
 * Punto de entrada principal para los seeders de DAGAZ.
 * Orquesta la ejecución ordenada de todos los seeders.
 *
 * Orden de ejecución:
 *  1. FuentesSeeder          → Grafo de credibilidad (80 fuentes IFCN/Nivel 1)
 *  2. UsuariosSeeder         → Usuarios de prueba (adulto, admin, editor)
 *  3. NoticiasColombiaSeeder → Noticias y verificaciones recientes de Colombia
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Ejecuta los seeders de la base de datos.
     */
    public function run(): void
    {
        $this->call([
            FuentesSeeder::class,
            UsuariosSeeder::class,
            NoticiasColombiaSeeder::class,
        ]);
    }
}
