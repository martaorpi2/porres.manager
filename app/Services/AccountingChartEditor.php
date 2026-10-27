<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountingEntryLine;
use App\Models\FundMovementImputation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AccountingChartEditor
{
    public function __construct(private AccountingChartReport $report)
    {
    }

    /**
     * @param  array{code: string, name: string, account_type: string|null, is_active: bool, balance_nature: string|null}  $payload
     */
    public function save(AccountingAccount $account, array $payload): int
    {
        $moved = 0;
        DB::transaction(function () use ($account, $payload, &$moved) {
            if ($account->code !== $payload['code']) {
                $descendants = $this->report->descendantAccounts($account->code);
                $this->rewrite($descendants, $account->code, $payload['code']);
                $moved = $descendants->count();
            }
            $account->update($payload + ['is_grouping' => $account->is_grouping]);
        });

        return $moved;
    }

    public function remove(AccountingAccount $account, string $action, ?string $targetCode): string
    {
        $descendants = $this->report->descendantAccounts($account->code);
        $parentCode = $this->report->parentOf($account->code);

        if ($descendants->isEmpty()) {
            $this->guardDeletable(collect([$account]));
            $account->delete();

            return $parentCode;
        }

        if (! in_array($action, ['move', 'delete'], true)) {
            throw new AccountingChartException('Antes de quitar el rubro, mové las cuentas hijas o eliminalas.');
        }

        if ($action === 'delete') {
            $this->guardDeletable($descendants->concat([$account]));
            DB::transaction(function () use ($descendants, $account) {
                AccountingAccount::query()->whereIn('id', $descendants->pluck('id'))->delete();
                $account->delete();
            });

            return $parentCode;
        }

        if ($action !== 'move' || $targetCode === null || $targetCode === '') {
            throw new AccountingChartException('Elegí el padre al que se mueven las cuentas hijas.');
        }

        $target = AccountingAccount::query()->where('code', $targetCode)->first();
        if (! $target) {
            throw new AccountingChartException('El padre destino no existe.');
        }
        if ((int) $target->id === (int) $account->id || $descendants->contains('id', $target->id)) {
            throw new AccountingChartException('Elegí un padre que no sea este rubro ni una cuenta que cuelga de él.');
        }

        DB::transaction(function () use ($account, $descendants, $target) {
            $this->rewrite($descendants, $account->code, $target->code);
            $this->guardDeletable(collect([$account]));
            $account->delete();
        });

        return $target->code;
    }

    /**
     * Cierra huecos en la numeración. Cada cuenta sigue bajo el mismo rubro.
     * Las líneas de los asientos guardan el id de la cuenta, así que muestran el código nuevo.
     */
    public function renumber(): int
    {
        return DB::transaction(function () {
            $before = AccountingAccount::query()->pluck('code', 'id');
            $this->renumberChildren('');
            $after = AccountingAccount::query()->pluck('code', 'id');
            $changed = 0;
            foreach ($before as $id => $code) {
                if (($after[$id] ?? null) !== $code) {
                    $changed++;
                }
            }

            return $changed;
        });
    }

    private function renumberChildren(string $parentCode): void
    {
        $children = $this->childrenOf($parentCode);
        $this->compactChildren($parentCode, $children);
        foreach ($this->childrenOf($parentCode) as $child) {
            $this->renumberChildren($child->code);
        }
    }

    /**
     * @param  Collection<int, AccountingAccount>  $children
     */
    private function compactChildren(string $parentCode, Collection $children): void
    {
        if ($parentCode !== '' && preg_match('/^\d+$/', $parentCode) !== 1) {
            return;
        }

        $groups = [];
        foreach ($children as $child) {
            if (preg_match('/^\d+$/', (string) $child->code) !== 1) {
                continue;
            }
            $groups[$this->trailingZeros((string) $child->code).'|'.strlen((string) $child->code)][] = $child;
        }

        $map = [];
        foreach ($groups as $group) {
            foreach ($this->planGroup($parentCode, collect($group)) as $id => $code) {
                $map[$id] = $code;
            }
        }
        if ($map === []) {
            return;
        }

        $this->assertAvailable($map);
        foreach ($map as $id => $code) {
            AccountingAccount::query()->whereKey($id)->update(['code' => 'tmp-'.$id]);
        }
        foreach ($map as $id => $code) {
            AccountingAccount::query()->whereKey($id)->update(['code' => $code]);
        }
    }

    /**
     * @param  Collection<int, AccountingAccount>  $children
     * @return array<int, string>
     */
    private function planGroup(string $parentCode, Collection $children): array
    {
        $sorted = $children->sortBy(fn (AccountingAccount $account) => (int) $account->code)->values();
        $parentValue = $parentCode === '' ? 0 : (int) $parentCode;
        $offsets = [];
        foreach ($sorted as $child) {
            $offset = (int) $child->code - $parentValue;
            if ($offset <= 0) {
                return [];
            }
            $offsets[] = $offset;
        }

        $step = count($offsets) === 1
            ? $this->stepFromOffset($offsets[0])
            : $this->gcdOf($offsets);
        if ($step < 1) {
            return [];
        }

        $planned = [];
        foreach ($sorted as $index => $child) {
            $code = $this->formatCode($parentValue + $step * ($index + 1), strlen((string) $child->code));
            if ($code === null || $this->trailingZeros($code) !== $this->trailingZeros((string) $child->code)) {
                return [];
            }
            $planned[] = [$child, $code];
        }

        $map = [];
        foreach ($planned as [$child, $code]) {
            if ($code === (string) $child->code) {
                continue;
            }
            $map[(int) $child->id] = $code;
            $oldStem = $this->stem((string) $child->code);
            $newStem = $this->stem($code);
            foreach ($this->report->descendantAccounts((string) $child->code) as $descendant) {
                $map[(int) $descendant->id] = $this->mappedCode((string) $descendant->code, $oldStem, $newStem);
            }
        }

        return $map;
    }

    /**
     * @return Collection<int, AccountingAccount>
     */
    private function childrenOf(string $parentCode): Collection
    {
        $codes = [];
        foreach ($this->report->rows() as $row) {
            if ($row['sums_to'] === $parentCode) {
                $codes[] = $row['code'];
            }
        }
        if ($codes === []) {
            return collect();
        }

        return AccountingAccount::query()->whereIn('code', $codes)->orderBy('code')->get();
    }

    private function trailingZeros(string $code): int
    {
        if (preg_match('/0+$/', $code, $match) !== 1) {
            return 0;
        }

        return strlen($match[0]);
    }

    private function stepFromOffset(int $offset): int
    {
        $text = (string) $offset;
        $zeros = strlen($text) - strlen(rtrim($text, '0'));

        return 10 ** $zeros;
    }

    /**
     * @param  list<int>  $values
     */
    private function gcdOf(array $values): int
    {
        $result = 0;
        foreach ($values as $value) {
            $result = $this->gcd($result, $value);
        }

        return $result;
    }

    private function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            $rest = $a % $b;
            $a = $b;
            $b = $rest;
        }

        return $a;
    }

    private function formatCode(int $number, int $width): ?string
    {
        $raw = (string) $number;
        if ($raw === '' || strlen($raw) > $width) {
            return null;
        }
        $code = str_pad($raw, $width, '0', STR_PAD_LEFT);
        if (str_starts_with($code, '0')) {
            return null;
        }

        return $code;
    }

    /**
     * @param  Collection<int, AccountingAccount>  $descendants
     */
    private function rewrite(Collection $descendants, string $fromCode, string $toCode): void
    {
        $oldStem = $this->stem($fromCode);
        $newStem = $this->stem($toCode);
        if ($descendants->isEmpty() || $oldStem === $newStem) {
            return;
        }

        $map = [];
        foreach ($descendants as $descendant) {
            $map[$descendant->id] = $this->mappedCode($descendant->code, $oldStem, $newStem);
        }
        $this->assertAvailable($map);

        foreach ($map as $id => $code) {
            AccountingAccount::query()->whereKey($id)->update(['code' => 'tmp-'.$id]);
        }
        foreach ($map as $id => $code) {
            AccountingAccount::query()->whereKey($id)->update(['code' => $code]);
        }
    }

    /**
     * @param  array<int, string>  $map
     */
    private function assertAvailable(array $map): void
    {
        $codes = array_values($map);
        if (count($codes) !== count(array_unique($codes))) {
            throw new AccountingChartException('El cambio de código hace coincidir dos cuentas hijas.');
        }

        $clash = AccountingAccount::query()
            ->whereIn('code', $codes)
            ->whereNotIn('id', array_keys($map))
            ->value('code');
        if ($clash) {
            throw new AccountingChartException('El código '.$clash.' ya está usado. Elegí otro padre o código.');
        }
    }

    /**
     * @param  Collection<int, AccountingAccount>  $accounts
     */
    private function guardDeletable(Collection $accounts): void
    {
        $blocked = [];
        foreach ($accounts as $account) {
            $used = AccountingEntryLine::query()->where('accounting_account_id', $account->id)->exists()
                || FundMovementImputation::query()->where('accounting_account_id', $account->id)->exists();
            if ($used) {
                $blocked[] = $account->code.' '.$account->name;
            }
        }
        if ($blocked !== []) {
            throw new AccountingChartException('No se puede eliminar porque tienen movimientos: '.implode(', ', $blocked).'.');
        }
    }

    private function mappedCode(string $code, string $oldStem, string $newStem): string
    {
        if (! str_starts_with($code, $oldStem)) {
            throw new AccountingChartException('La cuenta '.$code.' no cuelga de ese rubro.');
        }

        $next = $newStem.substr($code, strlen($oldStem));
        if (strlen($next) < strlen($code)) {
            $next = str_pad($next, strlen($code), '0');
        }
        if (strlen($next) > strlen($code) || strlen($next) > 30) {
            throw new AccountingChartException('Ese código no deja lugar para las cuentas hijas.');
        }

        return $next;
    }

    private function stem(string $code): string
    {
        $stem = rtrim($code, '0');

        return $stem === '' ? '0' : $stem;
    }
}
