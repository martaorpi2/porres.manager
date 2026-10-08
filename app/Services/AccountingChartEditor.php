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
