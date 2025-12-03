<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PurchaseAuthorizationLimit;

class PurchaseAuthorizationLimitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $limits = [
            [
                'role_name' => 'role_responsable_compras',
                'role_display_name' => 'Responsable de Compras',
                'limit_amount' => 360000.00,
                'description' => 'Límite de autorización para el responsable de compras',
                'is_active' => true,
            ],
            [
                'role_name' => 'role_admin_institucion',
                'role_display_name' => 'Administrador del Instituto',
                'limit_amount' => 350000.00,
                'description' => 'Límite de autorización para el administrador del instituto',
                'is_active' => true,
            ],
            [
                'role_name' => 'role_apoderado',
                'role_display_name' => 'Apoderado Legal',
                'limit_amount' => 600000.00,
                'description' => 'Límite de autorización para el apoderado legal',
                'is_active' => true,
            ],
            [
                'role_name' => 'role_representante_legal',
                'role_display_name' => 'Representante Legal',
                'limit_amount' => 11700000.00,
                'description' => 'Límite de autorización para el representante legal',
                'is_active' => true,
            ],
        ];

        foreach ($limits as $limit) {
            PurchaseAuthorizationLimit::updateOrCreate(
                ['role_name' => $limit['role_name']],
                $limit
            );
        }
    }
}
