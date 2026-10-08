<?php

namespace App\Services;

use App\Models\AccountingAccount;

/**
 * Arma el catálogo de cuentas con el formato del plan del instituto.
 * Los rubros de agrupación viven en la base y se pueden modificar.
 */
final class AccountingChartReport
{
    /**
     * @return list<array{code: string, name: string, sums_to: string, nature: string, balance: string, receives: string, level: int}>
     */
    public function rows(): array
    {
        $rows = [];
        $storedGroups = AccountingAccount::query()->where('is_grouping', true)->orderBy('code')->get();
        if ($storedGroups->isEmpty()) {
            foreach ($this->groups() as $code => $group) {
                $rows[$code] = [
                    'code' => $code,
                    'name' => $group['name'],
                    'sums_to' => '',
                    'nature' => '',
                    'balance' => '',
                    'receives' => '',
                ];
            }
        } else {
            foreach ($storedGroups as $account) {
                $rows[$account->code] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'sums_to' => '',
                    'nature' => '',
                    'balance' => '',
                    'receives' => '',
                ];
            }
        }

        foreach (AccountingAccount::query()->where('is_grouping', false)->orderBy('code')->get() as $account) {
            [$nature, $balance] = $this->natureAndBalance($account);
            $rows[$account->code] = [
                'code' => $account->code,
                'name' => $account->name,
                'sums_to' => '',
                'nature' => $nature,
                'balance' => $balance,
                'receives' => 'Si',
            ];
        }

        ksort($rows);
        foreach ($rows as $code => $row) {
            $rows[$code]['sums_to'] = $this->parentCode($code, $rows);
        }

        foreach ($rows as $code => $row) {
            $rows[$code]['level'] = $this->level($code, $rows);
        }

        return array_values($rows);
    }

    /**
     * @return list<array{id: int|null, code: string, name: string, sums_to: string, level: int, nature: string, balance: string, receives: bool, account_type: string|null, is_active: bool, children: list<array>}>
     */
    public function tree(): array
    {
        $accounts = AccountingAccount::query()->get()->keyBy(fn (AccountingAccount $account) => (string) $account->code);
        $byParent = [];
        foreach ($this->rows() as $row) {
            $byParent[$row['sums_to']][] = $row;
        }

        $build = function (string $parentCode) use (&$build, $byParent, $accounts): array {
            $nodes = [];
            foreach ($byParent[$parentCode] ?? [] as $row) {
                $account = $accounts->get($row['code']);
                $nodes[] = [
                    'id' => $account?->id,
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'sums_to' => $row['sums_to'],
                    'level' => $row['level'],
                    'nature' => $row['nature'],
                    'balance' => $row['balance'],
                    'receives' => $row['receives'] === 'Si',
                    'is_grouping' => $account ? (bool) $account->is_grouping : $row['receives'] !== 'Si',
                    'account_type' => $account?->account_type,
                    'is_active' => $account ? (bool) $account->is_active : true,
                    'children' => $build($row['code']),
                ];
            }

            return $nodes;
        };

        return $build('');
    }

    public function isGroupingCode(string $code): bool
    {
        if (AccountingAccount::query()->where('is_grouping', true)->exists()) {
            return AccountingAccount::query()->where('code', $code)->where('is_grouping', true)->exists();
        }

        return array_key_exists($code, $this->groups());
    }

    /**
     * @return array<string, array{name: string, sums_to: string}>
     */
    public function defaultGroups(): array
    {
        return $this->groups();
    }

    public function leafParentOf(string $code): ?AccountingAccount
    {
        $rows = [];
        foreach ($this->rows() as $row) {
            $rows[$row['code']] = $row;
        }

        $parentCode = $this->parentCode($code, $rows);
        if ($parentCode === '') {
            return null;
        }

        $parent = AccountingAccount::query()->where('code', $parentCode)->first();
        if (! $parent || $parent->is_grouping || $this->descendantAccounts($parentCode)->isNotEmpty()) {
            return null;
        }

        return $parent;
    }

    public function parentOf(string $code): string
    {
        foreach ($this->rows() as $row) {
            if ($row['code'] === $code) {
                return $row['sums_to'];
            }
        }

        return '';
    }

    /**
     * @return \Illuminate\Support\Collection<int, AccountingAccount>
     */
    public function descendantAccounts(string $code): \Illuminate\Support\Collection
    {
        $node = $this->findNode($this->tree(), $code);
        $ids = [];
        if ($node) {
            $this->collectIds($node['children'], $ids);
        }

        if ($ids === []) {
            return collect();
        }

        return AccountingAccount::query()->whereIn('id', $ids)->get();
    }

    /**
     * @param  list<array{code: string, children: list<array>}>  $nodes
     * @return array<string, mixed>|null
     */
    private function findNode(array $nodes, string $code): ?array
    {
        foreach ($nodes as $node) {
            if ($node['code'] === $code) {
                return $node;
            }
            $found = $this->findNode($node['children'], $code);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  list<array{id: int|null, children: list<array>}>  $nodes
     * @param  list<int>  $ids
     */
    private function collectIds(array $nodes, array &$ids): void
    {
        foreach ($nodes as $node) {
            if ($node['id']) {
                $ids[] = $node['id'];
            }
            $this->collectIds($node['children'], $ids);
        }
    }

    /**
     * @param  array<string, array{code: string, name: string, sums_to: string, nature: string, balance: string, receives: string}>  $rows
     */
    private function level(string $code, array $rows): int
    {
        $depth = 0;
        $guard = 0;
        while ($guard < 12 && isset($rows[$code]) && $rows[$code]['sums_to'] !== '') {
            $code = $rows[$code]['sums_to'];
            $depth++;
            $guard++;
        }

        return $depth;
    }

    /**
     * @param  array<string, array{code: string, name: string, sums_to: string, nature: string, balance: string, receives: string}>  $rows
     */
    private function parentCode(string $code, array $rows): string
    {
        $length = strlen($code);
        for ($keep = $length - 1; $keep >= 1; $keep--) {
            $candidate = substr($code, 0, $keep).str_repeat('0', $length - $keep);
            if ($candidate !== $code && isset($rows[$candidate])) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function natureAndBalance(AccountingAccount $account): array
    {
        return match ($account->account_type) {
            'activo' => ['Deudor', 'Activo'],
            'pasivo' => ['Acreedor', 'Pasivo'],
            'patrimonio' => ['Acreedor', 'Patrimonio'],
            'ingreso' => ['Acreedor', 'Ingreso'],
            'gasto' => ['Deudor', 'Egreso'],
            default => ['', ''],
        };
    }

    /**
     * @return array<string, array{name: string, sums_to: string}>
     */
    private function groups(): array
    {
        return [
            '10000000' => ['name' => 'ACTIVO', 'sums_to' => ''],
            '11000000' => ['name' => 'ACTIVO CORRIENTE', 'sums_to' => '10000000'],
            '11100000' => ['name' => 'CAJA Y BANCOS', 'sums_to' => '11000000'],
            '11101000' => ['name' => 'CAJA', 'sums_to' => '11100000'],
            '11102000' => ['name' => 'BANCOS', 'sums_to' => '11100000'],
            '11200000' => ['name' => 'CREDITOS POR SERV. EDUCATIVOS', 'sums_to' => '11000000'],
            '11202000' => ['name' => 'TARJETAS A COBRAR', 'sums_to' => '11200000'],
            '11300000' => ['name' => 'OTROS CREDITOS', 'sums_to' => '11000000'],
            '11400000' => ['name' => 'BIENES DE CONSUMO', 'sums_to' => '11000000'],
            '11500000' => ['name' => 'INVERSIONES CORTO PLAZO', 'sums_to' => '11000000'],
            '12000000' => ['name' => 'ACTIVO NO CORRIENTE', 'sums_to' => '10000000'],
            '12100000' => ['name' => 'BIENES DE USO', 'sums_to' => '12000000'],
            '12107000' => ['name' => 'INMUEBLES/EDIFICIOS', 'sums_to' => '12100000'],
            '12200000' => ['name' => 'BIENES INTANGIBLES', 'sums_to' => '12000000'],
            '12300000' => ['name' => 'INVERSIONES', 'sums_to' => '12000000'],
            '13000000' => ['name' => 'CUENTAS TRANSITORIAS', 'sums_to' => '10000000'],
            '20000000' => ['name' => 'PASIVO', 'sums_to' => ''],
            '21000000' => ['name' => 'PASIVOS CORRIENTES', 'sums_to' => '20000000'],
            '21100000' => ['name' => 'DEUDAS COMERCIALES', 'sums_to' => '21000000'],
            '21101000' => ['name' => 'PROVEEDORES A PAGAR', 'sums_to' => '21100000'],
            '21200000' => ['name' => 'DEUDAS SOCIALES', 'sums_to' => '21000000'],
            '21300000' => ['name' => 'DEUDAS FISCALES', 'sums_to' => '21000000'],
            '21400000' => ['name' => 'DEUDAS BANCARIAS', 'sums_to' => '21000000'],
            '21500000' => ['name' => 'OTRAS DEUDAS', 'sums_to' => '21000000'],
            '21600000' => ['name' => 'OTRAS DEUDAS', 'sums_to' => '21000000'],
            '30000000' => ['name' => 'PATRIMONIO NETO', 'sums_to' => ''],
            '31000000' => ['name' => 'CAPITAL, RESERVAS Y RESULTADOS', 'sums_to' => '30000000'],
            '31100000' => ['name' => 'CAPITAL', 'sums_to' => '31000000'],
            '31200000' => ['name' => 'RESERVAS', 'sums_to' => '31000000'],
            '31300000' => ['name' => 'RESULTADOS', 'sums_to' => '31000000'],
            '40000000' => ['name' => 'RECURSOS', 'sums_to' => ''],
            '41000000' => ['name' => 'INGRESOS', 'sums_to' => '40000000'],
            '41100000' => ['name' => 'INGRESOS POR SERVICIO', 'sums_to' => '41000000'],
            '41200000' => ['name' => 'INGRESOS FINANCIEROS', 'sums_to' => '41000000'],
            '41300000' => ['name' => 'OTROS INGRESOS', 'sums_to' => '41000000'],
            '52000000' => ['name' => 'GASTOS', 'sums_to' => ''],
            '52100000' => ['name' => 'GASTOS SECTOR ACADEMICO', 'sums_to' => '52000000'],
            '52200000' => ['name' => 'GASTOS DE ADMINISTRACION', 'sums_to' => '52000000'],
            '52300000' => ['name' => 'GASTOS FINANCIEROS', 'sums_to' => '52000000'],
            '52400000' => ['name' => 'GASTOS DE MANT. Y DESGASTE', 'sums_to' => '52000000'],
            '52500000' => ['name' => 'OTROS EGRESOS', 'sums_to' => '52000000'],
        ];
    }
}
