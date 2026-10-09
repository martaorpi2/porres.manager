<?php

namespace App\Http\Controllers\Admin;

use App\Models\AccountingAccount;
use App\Models\AccountingEntry;
use App\Models\AccountingEntryLine;
use App\Models\QuotaAccountingBatch;
use App\Models\QuotaAccountingCollectionDate;
use App\Models\User;
use App\Services\AccountingEntryEditor;
use App\Services\PaymentSettlement\PaymentSettlementChannels;
use App\Services\QuotaAccountingService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class AccountingJournalController extends CrudController
{
    public function index(Request $request)
    {
        $this->authorizeAccounting();

        $from = $this->dateOrNull($request->input('from'));
        $to = $this->dateOrNull($request->input('to'));
        $collected = $this->dateOrNull($request->input('collected'));
        $kind = $request->input('kind');
        $accountId = $request->integer('account_id') ?: null;

        $entries = $collected !== null
            ? $this->entriesForCollectionDate($collected, is_string($kind) ? $kind : null, $accountId)
            : AccountingEntry::query()
                ->with(['lines.account'])
                ->where('status', AccountingEntry::STATUS_POSTED)
                ->whereIn('kind', AccountingEntry::quotaKinds())
                ->when($from, fn ($query) => $query->whereDate('date', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('date', '<=', $to))
                ->when(in_array($kind, $this->kinds(), true), fn ($query) => $query->where('kind', $kind))
                ->when($accountId, function ($query) use ($accountId) {
                    $query->whereHas('lines', fn ($lines) => $lines->where('accounting_account_id', $accountId));
                })
                ->orderBy('date')
                ->orderBy('id')
                ->get();

        return view('admin.accounting.journal', [
            'entries' => $entries,
            'accounts' => $this->accounts(),
            'kinds' => $this->kindOptions(),
            'filters' => [
                'from' => $from,
                'to' => $to,
                'collected' => $collected,
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

    public function refresh(Request $request)
    {
        $this->authorizeAccounting();

        $from = $this->dateOrNull($request->input('from'));
        $to = $this->dateOrNull($request->input('to'));
        $months = $this->monthsInSelection($from, $to);
        $back = backpack_url('accounting-journal').'?'.http_build_query($this->returnQuery($request));

        if ($months === null) {
            \Alert::warning('Elegí un período de hasta 3 meses para actualizar.')->flash();

            return redirect($back);
        }

        set_time_limit(180);

        try {
            $service = app(QuotaAccountingService::class);
            $messages = [];
            if ($from === null && $to === null) {
                $messages[] = 'No había fechas elegidas, así que se tomó el mes en curso.';
                $month = $months[0];
                $back = backpack_url('accounting-journal').'?'.http_build_query($this->returnQuery($request) + [
                    'from' => $month->copy()->startOfMonth()->toDateString(),
                    'to' => $month->copy()->endOfMonth()->toDateString(),
                ]);
            }
            foreach ($months as $month) {
                $messages[] = $this->refreshSummary($service->postMonth($month));
            }
        } catch (Throwable $exception) {
            \Alert::error($exception->getMessage())->flash();

            return redirect($back);
        }

        \Alert::success(implode(' ', $messages))->flash();

        return redirect($back);
    }

    public function edit(Request $request, AccountingEntry $accountingEntry)
    {
        $this->authorizeAccounting();
        $this->ensureEditable($accountingEntry);
        $accountingEntry->load(['lines.account']);

        return view('admin.accounting.journal_edit', [
            'entry' => $accountingEntry,
            'accounts' => $this->editableAccounts($accountingEntry),
            'returnQuery' => $this->returnQuery($request),
            'title' => 'Modificar '.$accountingEntry->entry_number,
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Libro diario' => backpack_url('accounting-journal'),
                $accountingEntry->entry_number => false,
            ],
        ]);
    }

    public function update(Request $request, AccountingEntry $accountingEntry)
    {
        $this->authorizeAccounting();
        $this->ensureEditable($accountingEntry);

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
        ], [
            'description.required' => 'La descripción es obligatoria.',
            'description.max' => 'La descripción no puede superar los 255 caracteres.',
        ]);
        $description = trim($validated['description']);
        if ($description === '') {
            return back()->withInput()->withErrors(['description' => 'La descripción es obligatoria.']);
        }

        $prepared = AccountingEntryEditor::prepare($request->input('lines', []));
        if ($prepared['error'] !== null) {
            return back()->withInput()->withErrors(['lines' => $prepared['error']]);
        }

        $accountIds = array_values(array_unique(array_column($prepared['lines'], 'accounting_account_id')));
        $found = AccountingAccount::query()->whereIn('id', $accountIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($found) !== count($accountIds)) {
            return back()->withInput()->withErrors(['lines' => 'Hay una cuenta que no existe en el plan.']);
        }
        $grouping = AccountingAccount::groupingUsedMessage($accountIds);
        if ($grouping !== null) {
            return back()->withInput()->withErrors(['lines' => $grouping]);
        }

        $memos = $accountingEntry->lines()->pluck('memo', 'id');

        DB::transaction(function () use ($accountingEntry, $prepared, $memos, $description) {
            $accountingEntry->lines()->delete();
            foreach ($prepared['lines'] as $line) {
                $accountingEntry->lines()->create([
                    'accounting_account_id' => $line['accounting_account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'memo' => $line['source_id'] !== null ? $memos->get($line['source_id']) : null,
                ]);
            }
            $accountingEntry->update([
                'description' => $description,
                'manually_adjusted' => true,
            ]);
        });

        \Alert::success('El asiento '.$accountingEntry->entry_number.' quedó modificado.')->flash();

        return redirect(backpack_url('accounting-journal').'?'.http_build_query($this->returnQuery($request)));
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
            ->whereIn('e.kind', AccountingEntry::quotaKinds())
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

    /**
     * @return array<int, string>
     */
    private function editableAccounts(AccountingEntry $entry): array
    {
        $current = $entry->lines->pluck('accounting_account_id')->filter()->all();

        return AccountingAccount::query()
            ->where('is_grouping', false)
            ->where(function ($query) use ($current) {
                $query->where('is_active', true);
                if ($current !== []) {
                    $query->orWhereIn('id', $current);
                }
            })
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (AccountingAccount $account) => [$account->id => $account->identifying_label])
            ->all();
    }

    private function ensureEditable(AccountingEntry $entry): void
    {
        if ($entry->status !== AccountingEntry::STATUS_POSTED) {
            abort(403, 'Solo se pueden modificar asientos registrados.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function returnQuery(Request $request): array
    {
        $query = [];
        foreach (['from', 'to', 'collected', 'kind', 'account_id'] as $key) {
            $value = $request->input($key);
            if (is_string($value) && $value !== '') {
                $query[$key] = $value;
            }
        }

        return $query;
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
            AccountingEntry::KIND_QUOTA_GRANT => 'Becas otorgadas',
            AccountingEntry::KIND_QUOTA_COLLECTION => 'Cobranza de cuotas',
            AccountingEntry::KIND_QUOTA_MP_SETTLEMENT => 'Acreditación Mercado Pago',
            AccountingEntry::KIND_QUOTA_NX_SETTLEMENT => 'Acreditación Naranja X',
            AccountingEntry::KIND_QUOTA_SOL_SETTLEMENT => 'Acreditación Sol Pago',
            AccountingEntry::KIND_QUOTA_QR_SETTLEMENT => 'Acreditación QR',
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
            ->where('is_grouping', false)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (AccountingAccount $account) => [$account->id => $account->identifying_label])
            ->all();
    }

    /**
     * @return list<Carbon>|null
     */
    private function monthsInSelection(?string $from, ?string $to): ?array
    {
        if ($from === null && $to === null) {
            return [now()->startOfMonth()];
        }

        $start = Carbon::parse($from ?? $to)->startOfMonth();
        $end = Carbon::parse($to ?? $from)->startOfMonth();
        if ($end->lt($start)) {
            [$start, $end] = [$end->copy(), $start->copy()];
        }

        $months = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $months[] = $cursor->copy();
            if (count($months) > 3) {
                return null;
            }
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * Cobranza de ese día. El contrasiento entra solo si el tipo es una acreditación y no hay cuenta.
     *
     * @return \Illuminate\Support\Collection<int, AccountingEntry>
     */
    private function entriesForCollectionDate(string $collected, ?string $kind, ?int $accountId)
    {
        $kind = in_array($kind, $this->kinds(), true) ? $kind : null;
        $paymentType = $this->paymentTypeForKind($kind);
        $pairWithAccreditation = $accountId === null
            && in_array($kind, AccountingEntry::accreditationKinds(), true);

        $accreditationIds = collect();
        if ($pairWithAccreditation) {
            $accreditationIds = QuotaAccountingCollectionDate::query()
                ->whereDate('collected_on', $collected)
                ->whereHas('batch.entry', function ($query) use ($kind) {
                    $query->where('status', AccountingEntry::STATUS_POSTED)
                        ->where('kind', $kind);
                })
                ->with('batch')
                ->get()
                ->map(fn (QuotaAccountingCollectionDate $date) => $date->batch?->accounting_entry_id)
                ->filter()
                ->unique()
                ->values();
        }

        $collectionIds = $kind !== null && $paymentType === null && $kind !== AccountingEntry::KIND_QUOTA_COLLECTION
            ? collect()
            : QuotaAccountingBatch::query()
                ->where('kind', QuotaAccountingBatch::KIND_COLLECTION)
                ->whereDate('entry_date', $collected)
                ->when($paymentType !== null, fn ($query) => $query->where('payment_type', $paymentType))
                ->whereNotNull('accounting_entry_id')
                ->pluck('accounting_entry_id');

        if ($pairWithAccreditation && $accreditationIds->isEmpty()) {
            $accreditationIds = $this->accreditationIdsByAmount($kind, $collected, $collectionIds);
        }

        $ids = $pairWithAccreditation
            ? $collectionIds->merge($accreditationIds)->unique()->values()
            : $collectionIds;
        if ($ids->isEmpty()) {
            return collect();
        }

        return AccountingEntry::query()
            ->with(['lines.account'])
            ->where('status', AccountingEntry::STATUS_POSTED)
            ->whereIn('id', $ids)
            ->when($accountId, function ($query) use ($accountId) {
                $query->whereHas('lines', fn ($lines) => $lines->where('accounting_account_id', $accountId));
            })
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    private function paymentTypeForKind(?string $kind): ?string
    {
        if ($kind === null) {
            return null;
        }

        foreach (PaymentSettlementChannels::keys() as $channel) {
            $definition = PaymentSettlementChannels::get($channel);
            if ($definition['entry_kind'] === $kind) {
                return (string) $definition['payment_type'];
            }
        }

        return null;
    }

    /**
     * Acreditaciones de archivo subidas antes de guardar la fecha de cobro.
     * Si el haber de la cuenta a cobrar coincide con el debe de la cobranza, es el contrasiento.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $collectionIds
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function accreditationIdsByAmount(?string $kind, string $collected, $collectionIds)
    {
        $channel = $this->channelForKind($kind);
        if ($channel === null || $collectionIds->isEmpty()) {
            return collect();
        }

        $definition = PaymentSettlementChannels::get($channel);
        $accountId = AccountingAccount::query()->where('code', $definition['receivable'])->value('id');
        if ($accountId === null) {
            return collect();
        }

        $debitCents = (int) AccountingEntryLine::query()
            ->whereIn('accounting_entry_id', $collectionIds)
            ->where('accounting_account_id', $accountId)
            ->get()
            ->sum(fn (AccountingEntryLine $line) => (int) round(((float) $line->debit) * 100));
        if ($debitCents < 1) {
            return collect();
        }

        $matches = QuotaAccountingBatch::query()
            ->where('kind', $definition['batch_kind'])
            ->where(function ($query) use ($channel) {
                $query->where('batch_key', 'like', $channel.':%');
                if ($channel === PaymentSettlementChannels::MERCADOPAGO) {
                    $query->orWhere('batch_key', 'like', 'mp-settlement:excel:%');
                }
            })
            ->whereDoesntHave('collectionDates')
            ->whereNotNull('accounting_entry_id')
            ->whereHas('entry', function ($query) use ($definition) {
                $query->where('status', AccountingEntry::STATUS_POSTED)
                    ->where('kind', $definition['entry_kind']);
            })
            ->with('entry.lines')
            ->get()
            ->filter(function (QuotaAccountingBatch $batch) use ($accountId, $debitCents) {
                $creditCents = (int) $batch->entry->lines
                    ->where('accounting_account_id', (int) $accountId)
                    ->sum(fn (AccountingEntryLine $line) => (int) round(((float) $line->credit) * 100));

                return abs($creditCents - $debitCents) <= 2;
            })
            ->values();

        if ($matches->count() === 1) {
            QuotaAccountingCollectionDate::query()->firstOrCreate([
                'quota_accounting_batch_id' => $matches[0]->id,
                'collected_on' => $collected,
            ]);
            $matches[0]->unsetRelation('collectionDates');
            app(QuotaAccountingService::class)->syncAccreditationDescription($matches[0]);
        }

        return $matches->pluck('accounting_entry_id')->map(fn ($id) => (int) $id)->values();
    }

    private function channelForKind(?string $kind): ?string
    {
        if ($kind === null) {
            return null;
        }

        foreach (PaymentSettlementChannels::keys() as $channel) {
            if (PaymentSettlementChannels::get($channel)['entry_kind'] === $kind) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function refreshSummary(array $result): string
    {
        $label = Carbon::createFromFormat('!Y-m', $result['month'])->translatedFormat('F Y');
        $label = mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
        $bits = [];

        $created = count($result['collections']['groups']);
        $updated = count($result['collections']['updated'] ?? []);
        if ($created === 1) {
            $bits[] = '1 cobranza nueva';
        } elseif ($created > 1) {
            $bits[] = $created.' cobranzas nuevas';
        }
        if ($updated === 1) {
            $bits[] = '1 cobranza actualizada';
        } elseif ($updated > 1) {
            $bits[] = $updated.' cobranzas actualizadas';
        }

        $settlementCreated = count($result['settlements']['groups']);
        $settlementUpdated = count($result['settlements']['updated'] ?? []);
        if ($settlementCreated === 1) {
            $bits[] = '1 acreditación de Mercado Pago nueva';
        } elseif ($settlementCreated > 1) {
            $bits[] = $settlementCreated.' acreditaciones de Mercado Pago nuevas';
        }
        if ($settlementUpdated === 1) {
            $bits[] = '1 acreditación de Mercado Pago actualizada';
        } elseif ($settlementUpdated > 1) {
            $bits[] = $settlementUpdated.' acreditaciones de Mercado Pago actualizadas';
        }

        if (in_array($result['grants']['status'] ?? '', ['posted', 'updated'], true)) {
            $bits[] = 'becas actualizadas';
        }

        $adjusted = (int) ($result['collections']['skipped']['adjusted'] ?? 0)
            + (int) ($result['settlements']['skipped']['adjusted'] ?? 0);
        if (($result['grants']['status'] ?? '') === 'adjusted') {
            $adjusted++;
        }
        if ($adjusted === 1) {
            $bits[] = '1 asiento modificado a mano quedó sin reescribir';
        } elseif ($adjusted > 1) {
            $bits[] = $adjusted.' asientos modificados a mano quedaron sin reescribir';
        }

        if ($bits === []) {
            return $label.': la cobranza ya estaba al día.';
        }

        return $label.': '.implode(', ', $bits).'.';
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
