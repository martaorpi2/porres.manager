<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\CompleteDataSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Ejecutar seeder de límites de autorización de compras
        $this->call(PurchaseAuthorizationLimitSeeder::class);
        
        // Ejecutar seeder completo que genera todos los datos
        $this->call(CompleteDataSeeder::class);
    }
}
