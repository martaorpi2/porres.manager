<?php

namespace App\Http\Controllers\Admin;

use App\Models\AccountingAccount;
use App\Models\AccountingEntry;
use App\Models\AccountingEntryLine;
use App\Models\Eporres\Order;
use App\Models\QuotaAccountingBatch;
use App\Models\QuotaAccountingCollectionDate;
use App\Models\QuotaAccountingOrder;
use App\Models\User;
use App\Services\AccountingEntryEditor;
use App\Services\AccountingEntryLinks;
use App\Services\EporresDailyPaymentsReport;
use App\Services\PaymentSettlement\PaymentSettlementChannels;
use App\Services\QuotaAccountingService;
use App\Support\Eporres\ReportePagosDiaCriteria;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class AccountingJournalController extends CrudController
{
    public function index(Request $request)
    {
        $this->authorizeAccounting();

        $from = $this->dateOrNull($request->input('from'));
        $to = $this->dateOrNull($request->input('to'));
        $collected = $this->dateOrNull($request->input('collected'));
        if ($from === null && $to === null && $collected === null) {
            $from = now()->startOfMonth()->toDateString();
            $to = now()->endOfMonth()->toDateString();
        }
        $kind = $request->input('kind');
        $accountId = $request->integer('account_id') ?: null;

        $entries = $collected !== null
            ? $this->entriesForCollectionDate($collected, is_string($kind) ? $kind : null, $accountId)
            : AccountingEntry::query()
                ->with(['lines.account', 'relatedEntries', 'citingEntries'])
                ->where('status', AccountingEntry::STATUS_POSTED)
                ->whereIn('kind', AccountingEntry::bookKinds())
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

    public function create(Request $request)
    {
        $this->authorizeAccounting();

        $accounts = AccountingAccount::optionsForSelect();

        return view('admin.accounting.journal_create', [
            'accounts' => $accounts,
            'accountSides' => $this->accountSides($accounts),
            'relatedEntries' => $this->selectedRelated($request),
            'paymentType' => null,
            'title' => 'Nuevo asiento',
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Libro diario' => backpack_url('accounting-journal'),
                'Nuevo asiento' => false,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAccounting();

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
        ], [
            'date.required' => 'La fecha es obligatoria.',
            'date.date' => 'La fecha no es válida.',
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

        $accountError = $this->accountLineError(array_column($prepared['lines'], 'accounting_account_id'), true);
        if ($accountError !== null) {
            return back()->withInput()->withErrors(['lines' => $accountError]);
        }

        $linkError = $this->relatedLinkError($request);
        if ($linkError !== null) {
            return back()->withInput()->withErrors(['related_ids' => $linkError]);
        }

        $user = backpack_user();
        $date = Carbon::parse($validated['date'])->toDateString();

        try {
            $entry = DB::transaction(function () use ($prepared, $description, $user, $date, $request) {
                $entry = AccountingEntry::query()->create([
                    'entry_number' => AccountingEntry::nextEntryNumber(),
                    'date' => $date,
                    'kind' => AccountingEntry::KIND_MANUAL,
                    'status' => AccountingEntry::STATUS_POSTED,
                    'source_type' => null,
                    'source_id' => null,
                    'description' => $description,
                    'manually_adjusted' => true,
                    'created_by_id' => $user instanceof User ? $user->id : null,
                ]);
                foreach ($prepared['lines'] as $line) {
                    $entry->lines()->create([
                        'accounting_account_id' => $line['accounting_account_id'],
                        'debit' => $line['debit'],
                        'credit' => $line['credit'],
                    ]);
                }
                $this->syncRelated($request, $entry);

                return $entry;
            });
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['lines' => $exception->getMessage()]);
        }

        \Alert::success('El asiento '.$entry->entry_number.' quedó registrado.')->flash();

        return redirect(backpack_url('accounting-journal').'?'.http_build_query([
            'from' => $date,
            'to' => $date,
            'kind' => AccountingEntry::KIND_MANUAL,
        ]));
    }

    public function edit(Request $request, AccountingEntry $accountingEntry)
    {
        $this->authorizeAccounting();
        $this->ensureEditable($accountingEntry);
        $accountingEntry->load(['lines.account', 'relatedEntries']);

        $accounts = $this->editableAccounts($accountingEntry);

        return view('admin.accounting.journal_edit', [
            'entry' => $accountingEntry,
            'accounts' => $accounts,
            'accountSides' => $this->accountSides($accounts),
            'relatedEntries' => $this->selectedRelated($request, $accountingEntry),
            'paymentType' => $this->settlementPaymentType($accountingEntry),
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

        $rules = [
            'description' => ['required', 'string', 'max:255'],
        ];
        $messages = [
            'description.required' => 'La descripción es obligatoria.',
            'description.max' => 'La descripción no puede superar los 255 caracteres.',
        ];
        if ($accountingEntry->kind === AccountingEntry::KIND_MANUAL) {
            $rules['date'] = ['required', 'date'];
            $messages['date.required'] = 'La fecha es obligatoria.';
            $messages['date.date'] = 'La fecha no es válida.';
        }
        $validated = $request->validate($rules, $messages);
        $description = trim($validated['description']);
        if ($description === '') {
            return back()->withInput()->withErrors(['description' => 'La descripción es obligatoria.']);
        }

        $prepared = AccountingEntryEditor::prepare($request->input('lines', []));
        if ($prepared['error'] !== null) {
            return back()->withInput()->withErrors(['lines' => $prepared['error']]);
        }

        $accountError = $this->accountLineError(array_column($prepared['lines'], 'accounting_account_id'));
        if ($accountError !== null) {
            return back()->withInput()->withErrors(['lines' => $accountError]);
        }

        $linkError = $this->relatedLinkError($request, $accountingEntry->id);
        if ($linkError !== null) {
            return back()->withInput()->withErrors(['related_ids' => $linkError]);
        }

        $memos = $accountingEntry->lines()->pluck('memo', 'id');

        DB::transaction(function () use ($accountingEntry, $prepared, $memos, $description, $validated, $request) {
            $accountingEntry->lines()->delete();
            foreach ($prepared['lines'] as $line) {
                $accountingEntry->lines()->create([
                    'accounting_account_id' => $line['accounting_account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'memo' => $line['source_id'] !== null ? $memos->get($line['source_id']) : null,
                ]);
            }
            $changes = [
                'description' => $description,
                'manually_adjusted' => true,
            ];
            if ($accountingEntry->kind === AccountingEntry::KIND_MANUAL) {
                $changes['date'] = Carbon::parse($validated['date'])->toDateString();
            }
            $accountingEntry->update($changes);
            $this->syncRelated($request, $accountingEntry);
        });

        \Alert::success('El asiento '.$accountingEntry->entry_number.' quedó modificado.')->flash();

        return redirect(backpack_url('accounting-journal').'?'.http_build_query($this->returnQuery($request)));
    }

    public function show(AccountingEntry $accountingEntry)
    {
        $this->authorizeAccounting();
        $accountingEntry->load(['lines.account', 'relatedEntries', 'citingEntries']);

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

    public function search(Request $request)
    {
        $this->authorizeAccounting();
        $paymentType = $request->query('payment_type');

        $entries = AccountingEntryLinks::search(
            (string) $request->query('q', ''),
            $request->integer('exclude') ?: null,
            is_string($paymentType) ? $paymentType : null,
        );

        return response()->json($entries->map(fn (AccountingEntry $entry) => [
            'id' => $entry->id,
            'label' => $entry->relationLabel(),
        ])->values());
    }

    public function reconciliationEntries(Request $request, EporresDailyPaymentsReport $report)
    {
        $this->authorizeAccounting();
        $ids = $this->idList($request->query('entries'));
        $orderIds = $this->idList($request->query('orders'));
        $position = array_flip($ids);

        $entries = AccountingEntry::query()
            ->with(['lines.account', 'relatedEntries', 'citingEntries'])
            ->whereIn('id', $ids === [] ? [0] : $ids)
            ->where('status', AccountingEntry::STATUS_POSTED)
            ->whereIn('kind', array_merge(
                [
                    AccountingEntry::KIND_QUOTA_COLLECTION,
                    AccountingEntry::KIND_QUOTA_PLAN_COLLECTION,
                ],
                AccountingEntry::accreditationKinds(),
            ))
            ->get()
            ->sortBy(fn (AccountingEntry $entry) => $position[$entry->id] ?? 999)
            ->values();

        return view('admin.accounting.partials.reconciliation_entries', [
            'entries' => $entries,
            'missingCollection' => $request->boolean('missing'),
            'excluded' => $orderIds === [] ? null : $report->forOrderIds($orderIds),
        ]);
    }

    public function collectionCoupons(AccountingEntry $accountingEntry, EporresDailyPaymentsReport $report)
    {
        $this->authorizeAccounting();
        if ($accountingEntry->status !== AccountingEntry::STATUS_POSTED || ! $accountingEntry->showsCollectionCoupons()) {
            abort(404);
        }

        $entryIds = in_array($accountingEntry->kind, AccountingEntry::accreditationKinds(), true)
            ? $this->linkedEntryIds($accountingEntry, [AccountingEntry::KIND_QUOTA_COLLECTION])
            : [$accountingEntry->id];

        return view('admin.accounting.partials.daily_payment_orders', $report->forOrderIds($this->orderIdsOf($entryIds)));
    }

    public function entryPlans(AccountingEntry $accountingEntry)
    {
        $this->authorizeAccounting();
        if ($accountingEntry->status !== AccountingEntry::STATUS_POSTED || ! $accountingEntry->showsPaymentPlans()) {
            abort(404);
        }

        $opening = $accountingEntry->kind === AccountingEntry::KIND_QUOTA_PLAN;
        $entryIds = in_array($accountingEntry->kind, AccountingEntry::accreditationKinds(), true)
            ? $this->linkedEntryIds($accountingEntry, [AccountingEntry::KIND_QUOTA_PLAN_COLLECTION])
            : [$accountingEntry->id];
        $stored = $this->mergedPlanOrders($entryIds);
        $eporres = $stored === []
            ? collect()
            : Order::query()
                ->whereIn('id', array_column($stored, 'id'))
                ->with(['student', 'student_plan'])
                ->get()
                ->keyBy('id');
        $related = $opening
            ? ReportePagosDiaCriteria::planInstallmentsByPlanId($eporres)
            : ReportePagosDiaCriteria::coveredArancelByPlanId($eporres);

        $rows = [];
        foreach ($stored as $order) {
            $pago = $eporres->get($order['id']);
            $plan = $pago?->student_plan;
            $rows[] = [
                'id' => $order['id'],
                'student' => $pago?->student
                    ? $pago->student->last_name.', '.$pago->student->first_name.' - '.($pago->student->dni ?: 's/d')
                    : '—',
                'plan' => $plan ? '#'.$plan->id : '—',
                'quota' => $pago ? ReportePagosDiaCriteria::labelNumeroCuota($pago, $plan) : '—',
                'debtors' => $order['debtors'],
                'interest' => $order['interest'],
                'financing' => $order['financing'],
                'bank' => $order['bank'],
                'note' => $pago ? ReportePagosDiaCriteria::notaVinculo($pago, $related) : '',
            ];
        }

        return view('admin.accounting.partials.plan_orders', [
            'rows' => $rows,
            'heading' => $opening ? 'Cuotas incluidas en el alta del plan' : 'Cuotas de plan de pago',
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
            ->whereIn('e.kind', AccountingEntry::bookKinds())
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
     * @param  list<string>  $kinds
     * @return list<int>
     */
    private function linkedEntryIds(AccountingEntry $entry, array $kinds): array
    {
        $linked = $entry->relatedEntries()->pluck('accounting_entries.id');
        if ($linked->isNotEmpty()) {
            return $entry->relatedEntries()
                ->whereIn('accounting_entries.kind', $kinds)
                ->pluck('accounting_entries.id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $batch = $entry->batch()->with('collectionDates')->first();
        if ($batch === null || $batch->payment_type === null || $batch->payment_type === '') {
            return [];
        }
        $dates = $batch->collectionDates
            ->map(fn ($row) => $row->collected_on?->toDateString())
            ->filter()
            ->unique()
            ->values()
            ->all();
        if ($dates === []) {
            return [];
        }

        $batchKinds = [];
        if (in_array(AccountingEntry::KIND_QUOTA_COLLECTION, $kinds, true)) {
            $batchKinds[] = QuotaAccountingBatch::KIND_COLLECTION;
        }
        if (in_array(AccountingEntry::KIND_QUOTA_PLAN_COLLECTION, $kinds, true)) {
            $batchKinds[] = QuotaAccountingBatch::KIND_PLAN_COLLECTION;
        }
        if ($batchKinds === []) {
            return [];
        }

        return QuotaAccountingBatch::query()
            ->whereIn('kind', $batchKinds)
            ->where('payment_type', $batch->payment_type)
            ->whereIn('entry_date', $dates)
            ->whereNotNull('accounting_entry_id')
            ->whereHas('entry', function ($query) use ($kinds) {
                $query->where('status', AccountingEntry::STATUS_POSTED)
                    ->whereIn('kind', $kinds);
            })
            ->pluck('accounting_entry_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $entryIds
     * @return list<int>
     */
    private function orderIdsOf(array $entryIds): array
    {
        if ($entryIds === []) {
            return [];
        }

        return QuotaAccountingOrder::query()
            ->whereHas('batch', fn ($query) => $query->whereIn('accounting_entry_id', $entryIds))
            ->pluck('eporres_order_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $entryIds
     * @return list<array{id: int, debtors: float, interest: float, financing: float, bank: float}>
     */
    private function mergedPlanOrders(array $entryIds): array
    {
        if ($entryIds === []) {
            return [];
        }

        $merged = [];
        $orders = QuotaAccountingOrder::query()
            ->whereHas('batch', fn ($query) => $query->whereIn('accounting_entry_id', $entryIds))
            ->orderBy('eporres_order_id')
            ->get();
        foreach ($orders as $order) {
            $id = (int) $order->eporres_order_id;
            if (! isset($merged[$id])) {
                $merged[$id] = ['id' => $id, 'debtors' => 0.0, 'interest' => 0.0, 'financing' => 0.0, 'bank' => 0.0];
            }
            $merged[$id]['debtors'] += (float) $order->debtors_amount;
            $merged[$id]['interest'] += (float) $order->interest_amount;
            $merged[$id]['financing'] += (float) $order->financing_amount;
            $merged[$id]['bank'] += (float) $order->bank_amount;
        }
        ksort($merged);

        return array_values($merged);
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

    /**
     * @param  array<int, string>  $accounts
     * @return array<int, string>
     */
    private function accountSides(array $accounts): array
    {
        if ($accounts === []) {
            return [];
        }

        return AccountingAccount::query()
            ->whereIn('id', array_keys($accounts))
            ->get()
            ->mapWithKeys(fn (AccountingAccount $account) => [$account->id => $account->entrySide() ?? ''])
            ->all();
    }

    /**
     * @param  list<int|string>  $accountIds
     */
    private function accountLineError(array $accountIds, bool $requireActive = false): ?string
    {
        $accountIds = array_values(array_unique(array_map('intval', $accountIds)));
        $found = AccountingAccount::query()->whereIn('id', $accountIds)->get();
        if ($found->count() !== count($accountIds)) {
            return 'Hay una cuenta que no existe en el plan.';
        }
        if ($requireActive && $found->contains(fn (AccountingAccount $account) => ! $account->is_active)) {
            return 'Hay una cuenta que no está activa.';
        }

        return AccountingAccount::groupingUsedMessage($accountIds);
    }

    private function ensureEditable(AccountingEntry $entry): void
    {
        if ($entry->status !== AccountingEntry::STATUS_POSTED) {
            abort(403, 'Solo se pueden modificar asientos registrados.');
        }
    }

    private function selectedRelated(Request $request, ?AccountingEntry $entry = null)
    {
        if (old('related_ids_present')) {
            return AccountingEntryLinks::findForForm(array_map('intval', (array) old('related_ids', [])));
        }

        return $entry?->relatedEntries ?? collect();
    }

    private function relatedLinkError(Request $request, ?int $selfId = null): ?string
    {
        if (! $request->boolean('related_ids_present')) {
            return null;
        }

        return AccountingEntryLinks::validateIds((array) $request->input('related_ids', []), $selfId);
    }

    private function syncRelated(Request $request, AccountingEntry $entry): void
    {
        if (! $request->boolean('related_ids_present')) {
            return;
        }

        AccountingEntryLinks::replace($entry, (array) $request->input('related_ids', []));
    }

    private function settlementPaymentType(AccountingEntry $entry): ?string
    {
        $kind = QuotaAccountingBatch::query()->where('accounting_entry_id', $entry->id)->value('kind');
        if (! is_string($kind)) {
            return null;
        }

        return PaymentSettlementChannels::forBatchKind($kind)['payment_type'] ?? null;
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

    /**
     * @return list<int>
     */
    private function idList(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $value) as $part) {
            if (preg_match('/^\d+$/', $part) === 1) {
                $ids[] = (int) $part;
            }
        }

        return array_slice(array_values(array_unique($ids)), 0, 500);
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
            AccountingEntry::KIND_QUOTA_MP_SETTLEMENT => 'Acreditación Mercado Pago',
            AccountingEntry::KIND_QUOTA_NX_SETTLEMENT => 'Acreditación Naranja X',
            AccountingEntry::KIND_QUOTA_QR_SETTLEMENT => 'Acreditación QR',
            AccountingEntry::KIND_QUOTA_SOL_SETTLEMENT => 'Acreditación Sol Pago',
            AccountingEntry::KIND_MANUAL => 'Asiento manual',
            AccountingEntry::KIND_QUOTA_GRANT => 'Becas otorgadas',
            AccountingEntry::KIND_QUOTA_COLLECTION => 'Cobranza de cuotas',
            AccountingEntry::KIND_QUOTA_PLAN => 'Alta de plan de pago',
            AccountingEntry::KIND_QUOTA_PLAN_COLLECTION => 'Cobranza de plan de pago',
            AccountingEntry::KIND_QUOTA_ACCRUAL => 'Devengamiento de cuotas',
            AccountingEntry::KIND_PAYROLL_ACCRUAL => 'Devengamiento de Sueldos',
            AccountingEntry::KIND_BANK_EXPENSE => 'Gastos Bancarios',
            AccountingEntry::KIND_PAYROLL_PAYMENT => 'Pago de Sueldos',
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
        $accreditationKind = in_array($kind, AccountingEntry::accreditationKinds(), true) ? $kind : null;
        $pairWithAccreditation = $accountId === null && (
            $accreditationKind !== null
            || $kind === null
            || $kind === AccountingEntry::KIND_QUOTA_COLLECTION
        );

        $accreditationIds = collect();
        if ($pairWithAccreditation) {
            $accreditationIds = QuotaAccountingCollectionDate::query()
                ->whereDate('collected_on', $collected)
                ->whereHas('batch.entry', function ($query) use ($accreditationKind) {
                    $query->where('status', AccountingEntry::STATUS_POSTED);
                    if ($accreditationKind !== null) {
                        $query->where('kind', $accreditationKind);
                    } else {
                        $query->whereIn('kind', AccountingEntry::accreditationKinds());
                    }
                })
                ->with('batch')
                ->get()
                ->map(fn (QuotaAccountingCollectionDate $date) => $date->batch?->accounting_entry_id)
                ->filter()
                ->unique()
                ->values();
        }

        $batchKinds = $this->collectedBatchKinds($kind);
        $batches = $batchKinds === []
            ? collect()
            : QuotaAccountingBatch::query()
                ->whereIn('kind', $batchKinds)
                ->whereDate('entry_date', $collected)
                ->when($paymentType !== null, fn ($query) => $query->where('payment_type', $paymentType))
                ->whereNotNull('accounting_entry_id')
                ->get(['accounting_entry_id', 'kind']);
        $collectionIds = $batches->pluck('accounting_entry_id');
        $regularCollectionIds = $batches
            ->where('kind', QuotaAccountingBatch::KIND_COLLECTION)
            ->pluck('accounting_entry_id')
            ->values();

        if ($pairWithAccreditation && $accreditationIds->isEmpty()) {
            $accreditationIds = $this->accreditationIdsByAmount($kind, $collected, $regularCollectionIds);
        }

        $ids = $pairWithAccreditation
            ? $collectionIds->merge($accreditationIds)->unique()->values()
            : $collectionIds;
        if ($ids->isEmpty()) {
            return collect();
        }

        return AccountingEntry::query()
            ->with(['lines.account', 'relatedEntries', 'citingEntries'])
            ->where('status', AccountingEntry::STATUS_POSTED)
            ->whereIn('id', $ids)
            ->when($accountId, function ($query) use ($accountId) {
                $query->whereHas('lines', fn ($lines) => $lines->where('accounting_account_id', $accountId));
            })
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    private function collectedBatchKinds(?string $kind): array
    {
        if ($kind === AccountingEntry::KIND_QUOTA_PLAN_COLLECTION) {
            return [QuotaAccountingBatch::KIND_PLAN_COLLECTION];
        }
        if ($kind === AccountingEntry::KIND_QUOTA_COLLECTION) {
            return [QuotaAccountingBatch::KIND_COLLECTION];
        }
        if ($kind === null || $this->paymentTypeForKind($kind) !== null) {
            return [
                QuotaAccountingBatch::KIND_COLLECTION,
                QuotaAccountingBatch::KIND_PLAN_COLLECTION,
            ];
        }

        return [];
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
            app(AccountingEntryLinks::class)->syncFromCollectionDates($matches[0]);
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
        $removed = (int) ($result['settlements']['removed'] ?? 0);
        if ($removed === 1) {
            $bits[] = '1 acreditación dada de baja porque no salió de un archivo';
        } elseif ($removed > 1) {
            $bits[] = $removed.' acreditaciones dadas de baja porque no salieron de un archivo';
        }
        $leftOut = (int) ($result['settlements']['skipped']['amount_mismatch'] ?? 0)
            + (int) ($result['settlements']['skipped']['missing_paid_at'] ?? 0);
        if ($leftOut === 1) {
            $bits[] = '1 pago de Mercado Pago no entró al asiento';
        } elseif ($leftOut > 1) {
            $bits[] = $leftOut.' pagos de Mercado Pago no entraron al asiento';
        }

        if (in_array($result['grants']['status'] ?? '', ['posted', 'updated'], true)) {
            $bits[] = 'becas actualizadas';
        }

        $plans = count($result['plans']['groups'] ?? []) + count($result['plans']['updated'] ?? []);
        if ($plans === 1) {
            $bits[] = '1 alta de plan de pago';
        } elseif ($plans > 1) {
            $bits[] = $plans.' altas de plan de pago';
        }
        $planCollections = count($result['plan_collections']['groups'] ?? []) + count($result['plan_collections']['updated'] ?? []);
        if ($planCollections === 1) {
            $bits[] = '1 cobranza de plan de pago';
        } elseif ($planCollections > 1) {
            $bits[] = $planCollections.' cobranzas de plan de pago';
        }
        $planSkipped = $result['plan_collections']['skipped'] ?? [];
        $planSplit = (int) ($planSkipped['split'] ?? 0);
        if ($planSplit === 1) {
            $bits[] = '1 pago de plan dividido no entró al asiento';
        } elseif ($planSplit > 1) {
            $bits[] = $planSplit.' pagos de plan divididos no entraron al asiento';
        }
        $planZero = (int) ($planSkipped['zero'] ?? 0);
        if ($planZero === 1) {
            $bits[] = '1 pago de plan sin importe no entró al asiento';
        } elseif ($planZero > 1) {
            $bits[] = $planZero.' pagos de plan sin importe no entraron al asiento';
        }
        foreach ($planSkipped['unmapped'] ?? [] as $type => $count) {
            $count = (int) $count;
            $bits[] = $count.' pago'.($count === 1 ? '' : 's').' de plan sin cuenta de fondos ('.$type.')';
        }

        $adjusted = (int) ($result['collections']['skipped']['adjusted'] ?? 0)
            + (int) ($result['settlements']['skipped']['adjusted'] ?? 0)
            + (int) ($result['plans']['skipped']['adjusted'] ?? 0)
            + (int) ($planSkipped['adjusted'] ?? 0);
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
