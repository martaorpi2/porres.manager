<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\CleanDatabaseSeeder;
use Database\Seeders\EducationalHealthDataSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Limpiar base de datos (excepto users)
        $this->call(CleanDatabaseSeeder::class);
        
        // Generar datos educativos y de salud
        $this->call(EducationalHealthDataSeeder::class);
    }
}
