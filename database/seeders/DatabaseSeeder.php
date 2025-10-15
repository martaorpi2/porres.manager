<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\TestDataSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\AdditionalTestDataSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed full test dataset
        $this->call(TestDataSeeder::class);
        
        // Seed inventory data
        $this->call(InventorySeeder::class);
        
        // Seed additional test data for applications, devolutions, market_rates, etc.
        $this->call(AdditionalTestDataSeeder::class);
    }
}
