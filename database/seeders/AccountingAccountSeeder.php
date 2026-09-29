<?php

namespace Database\Seeders;

use App\Models\AccountingAccount;
use Illuminate\Database\Seeder;
use RuntimeException;

class AccountingAccountSeeder extends Seeder
{
    /**
     * Cuentas imputables del plan del instituto (las que reciben movimientos).
     * Los rubros de agrupación no se cargan: el sistema no tiene jerarquía
     * y aparecerían como cuentas seleccionables.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/chart_of_accounts.json');
        $rows = json_decode((string) file_get_contents($path), true);
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('No se pudo leer el plan de cuentas.');
        }

        foreach ($rows as $row) {
            AccountingAccount::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'account_type' => $row['account_type'],
                    'is_active' => true,
                ]
            );
        }
    }
}
