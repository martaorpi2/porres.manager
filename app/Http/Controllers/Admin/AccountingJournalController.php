<?php

namespace App\Http\Controllers\Admin;

use App\Models\AccountingAccount;
use App\Models\AccountingEntry;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingJournalController extends CrudController
{
    public function index(Request $request)
    {
        $this->authorizeAccounting();

        $from = $this->dateOrNull($request->input('from'));
        $to = $this->dateOrNull($request->input('to'));
        $kind = $request->input('kind');
        $accountId = $request->integer('account_id') ?: null;

        $entries = AccountingEntry::query()
            ->withSum('lines as debit_total', 'debit')
            ->withSum('lines as credit_total', 'credit')
            ->when($from, fn ($query) => $query->whereDate('date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('date', '<=', $to))
            ->when(in_array($kind, $this->kinds(), true), fn ($query) => $query->where('kind', $kind))
            ->when($accountId, function ($query) use ($accountId) {
                $query->whereHas('lines', fn ($lines) => $lines->where('accounting_account_id', $accountId));
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.accounting.journal', [
            'entries' => $entries,
            'accounts' => $this->accounts(),
            'kinds' => $this->kindOptions(),
            'filters' => [
                'from' => $from,
                'to' => $to,
                'kind' => $kind,
                'account_id' => $accountId,
            ],
            'title' => 'Libro diario',
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Libro diario' => false,
            ],
        ]);
    }

    public function show(AccountingEntry $accountingEntry)
    {
        $this->authorizeAccounting();
        $accountingEntry->load(['lines.account']);

        return view('admin.accounting.journal_show', [
            'entry' => $accountingEntry,
            'title' => 'Asiento '.$accountingEntry->entry_number,
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Libro diario' => backpack_url('accounting-journal'),
                $accountingEntry->entry_number => false,
            ],
        ]);
    }

    public function ledger(Request $request)
    {
        $this->authorizeAccounting();

        $from = $this->dateOrNull($request->input('from'));
        $to = $this->dateOrNull($request->input('to'));

        $rows = DB::table('accounting_entry_lines as l')
            ->join('accounting_entries as e', 'e.id', '=', 'l.accounting_entry_id')
            ->join('accounting_accounts as a', 'a.id', '=', 'l.accounting_account_id')
            ->where('e.status', AccountingEntry::STATUS_POSTED)
            ->when($from, fn ($query) => $query->whereDate('e.date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('e.date', '<=', $to))
            ->groupBy('a.id', 'a.code', 'a.name', 'a.account_type')
            ->orderBy('a.code')
            ->selectRaw('a.id, a.code, a.name, a.account_type, SUM(l.debit) as debit, SUM(l.credit) as credit')
            ->get();

        return view('admin.accounting.ledger', [
            'rows' => $rows,
            'filters' => ['from' => $from, 'to' => $to],
            'title' => 'Sumas y saldos',
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Sumas y saldos' => false,
            ],
        ]);
    }

    private function authorizeAccounting(): void
    {
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canViewAccounting()) {
            abort(403, 'No tiene permiso para ver la contabilidad.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function kindOptions(): array
    {
        return [
            AccountingEntry::KIND_QUOTA_ACCRUAL => 'Devengamiento de cuotas',
            AccountingEntry::KIND_QUOTA_COLLECTION => 'Cobranza de cuotas',
            AccountingEntry::KIND_OUTFLOW => 'Egreso',
            AccountingEntry::KIND_REVERSAL => 'Reverso',
        ];
    }

    /**
     * @return list<string>
     */
    private function kinds(): array
    {
        return array_keys($this->kindOptions());
    }

    /**
     * @return array<int, string>
     */
    private function accounts(): array
    {
        return AccountingAccount::query()
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (AccountingAccount $account) => [$account->id => $account->identifying_label])
            ->all();
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date->toDateString();
    }
}
