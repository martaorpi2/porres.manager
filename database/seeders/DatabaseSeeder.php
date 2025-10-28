<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\CleanDatabaseSeeder;
use Database\Seeders\EducationalHealthDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Crear roles y permisos
        $this->call(RolesAndPermissionsSeeder::class);
        
        // Limpiar base de datos (excepto users)
        $this->call(CleanDatabaseSeeder::class);
        
        // Generar datos educativos y de salud
        $this->call(EducationalHealthDataSeeder::class);
    }
}
