<?php

namespace App\Http\Controllers\Admin;

use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Models\User;
use App\Services\PaymentSettlement\NaranjaSettlementFile;
use App\Services\PaymentSettlement\PaymentSettlementChannels;
use App\Services\PaymentSettlement\QrSettlementFile;
use App\Services\PaymentSettlement\SolSettlementFile;
use App\Services\QuotaAccountingService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class PaymentSettlementController extends CrudController
{
    public function choose()
    {
        $this->authorizeAccounting();

        return view('admin.accounting.payment_settlement_import', $this->pageData(null, null));
    }

    public function index(string $channel)
    {
        $this->authorizeAccounting();
        $definition = $this->definition($channel);

        return view('admin.accounting.payment_settlement_import', $this->pageData($channel, $definition));
    }

    public function preview(
        Request $request,
        string $channel,
        NaranjaSettlementFile $naranja,
        SolSettlementFile $sol,
        QrSettlementFile $qr,
    ) {
        $this->authorizeAccounting();
        $definition = $this->definition($channel);

        $request->validate([
            'archivo' => ['required', 'file', 'max:10240'],
        ], [
            'archivo.required' => $definition['empty_error'],
            'archivo.max' => 'El archivo no puede superar los 10 MB.',
        ]);

        $file = $request->file('archivo');
        $extension = strtolower((string) $file?->getClientOriginalExtension());
        if (! in_array($extension, $definition['extensions'], true)) {
            return back()->withErrors(['archivo' => $definition['extension_error']]);
        }

        try {
            $parsed = match ($channel) {
                PaymentSettlementChannels::NARANJA => $naranja->read($file->getRealPath()),
                PaymentSettlementChannels::SOL => $sol->read($file->getRealPath()),
                PaymentSettlementChannels::QR => $qr->read($file->getRealPath()),
                default => throw new RuntimeException('Medio de pago desconocido.'),
            };
        } catch (RuntimeException $exception) {
            return back()->withErrors(['archivo' => $exception->getMessage()]);
        } catch (Throwable) {
            return back()->withErrors(['archivo' => 'No se pudo leer el archivo de '.$definition['menu'].'.']);
        }

        $posted = $this->postedKeys(array_column($parsed['groups'], 'key'));
        $rows = [];
        foreach ($parsed['rows'] as $row) {
            $already = isset($posted[$row['group_key']]);
            $row['outcome'] = $already ? 'already_posted' : 'ready';
            $row['detail'] = $already
                ? 'Esta liquidación ya tiene asiento.'
                : 'Se incluye en el asiento del '.$this->displayDate($this->groupDate($parsed['groups'], $row['group_key'])).'.';
            $rows[] = $row;
        }
        $ready = [];
        foreach ($parsed['groups'] as $group) {
            if (! isset($posted[$group['key']]) && $this->groupIsPostable($definition, $group)) {
                $ready[] = $group;
            }
        }

        $token = (string) str()->uuid();
        $request->session()->put('payment_settlement_import', [
            'token' => $token,
            'channel' => $channel,
            'ready' => $ready,
        ]);

        return view('admin.accounting.payment_settlement_import', array_merge($this->pageData($channel, $definition), [
            'rows' => $rows,
            'ready' => $ready,
            'token' => $token,
            'document' => $parsed['document'],
        ]));
    }

    public function store(Request $request, string $channel, QuotaAccountingService $accounting)
    {
        $this->authorizeAccounting();
        $definition = $this->definition($channel);

        $saved = $request->session()->get('payment_settlement_import');
        $token = (string) $request->input('token');
        if (! is_array($saved) || ($saved['channel'] ?? '') !== $channel || $token === '' || ! hash_equals((string) ($saved['token'] ?? ''), $token)) {
            \Alert::warning('La vista previa venció. Volvé a subir el archivo.')->flash();

            return redirect(backpack_url('accounting-settlement/'.$channel));
        }

        $ready = $this->readyGroups($definition, $saved['ready'] ?? []);
        if ($ready === []) {
            \Alert::warning('No hay liquidaciones para registrar.')->flash();

            return redirect(backpack_url('accounting-settlement/'.$channel));
        }

        try {
            $entries = $accounting->postImportedPaymentSettlements($channel, $ready);
        } catch (Throwable $exception) {
            \Alert::error($exception->getMessage())->flash();

            return redirect(backpack_url('accounting-settlement/'.$channel));
        }

        $request->session()->forget('payment_settlement_import');

        if ($entries === []) {
            \Alert::warning('Esa liquidación ya tenía asiento.')->flash();

            return redirect(backpack_url('accounting-settlement/'.$channel));
        }

        $numbers = array_map(fn ($entry) => $entry->entry_number, $entries);
        $dates = array_map(fn ($entry) => $entry->date->toDateString(), $entries);
        sort($dates);

        \Alert::success('Se registraron los asientos '.implode(', ', $numbers).'.')->flash();

        return redirect(backpack_url('accounting-journal').'?'.http_build_query([
            'from' => $dates[0],
            'to' => $dates[array_key_last($dates)],
            'kind' => $definition['entry_kind'],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $channel): array
    {
        if (! in_array($channel, PaymentSettlementChannels::keys(), true)) {
            abort(404);
        }

        return PaymentSettlementChannels::get($channel);
    }

    /**
     * @param  array<string, mixed>|null  $definition
     * @return array<string, mixed>
     */
    private function pageData(?string $channel, ?array $definition): array
    {
        return [
            'title' => 'Acreditaciones',
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Libro diario' => backpack_url('accounting-journal'),
                'Acreditaciones' => false,
            ],
            'channel' => $channel,
            'definition' => $definition,
            'rows' => null,
            'ready' => [],
            'token' => null,
            'document' => null,
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, true>
     */
    private function postedKeys(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $posted = [];
        $found = QuotaAccountingBatch::query()
            ->whereIn('batch_key', $keys)
            ->whereHas('entry', fn ($query) => $query->where('status', AccountingEntry::STATUS_POSTED))
            ->pluck('batch_key');
        foreach ($found as $key) {
            $posted[(string) $key] = true;
        }

        return $posted;
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     */
    private function groupDate(array $groups, string $key): string
    {
        foreach ($groups as $group) {
            if ($group['key'] === $key) {
                return (string) $group['date'];
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $group
     */
    private function groupIsPostable(array $definition, array $group): bool
    {
        return $this->readyGroups($definition, [$group]) !== [];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  mixed  $groups
     * @return list<array{key: string, date: string, gross_cents: int, commission_cents: int, interest_cents: int, net_cents: int, document: string}>
     */
    private function readyGroups(array $definition, mixed $groups): array
    {
        if (! is_array($groups)) {
            return [];
        }

        $ready = [];
        foreach ($groups as $group) {
            if (! is_array($group)) {
                return [];
            }
            $key = (string) ($group['key'] ?? '');
            $date = (string) ($group['date'] ?? '');
            $document = (string) ($group['document'] ?? '');
            $gross = (int) ($group['gross_cents'] ?? 0);
            $commission = (int) ($group['commission_cents'] ?? 0);
            $interest = (int) ($group['interest_cents'] ?? 0);
            $net = (int) ($group['net_cents'] ?? 0);
            if (preg_match('/^[A-Za-z0-9:.\-]+$/', $key) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || $document === '') {
                return [];
            }
            if ($gross < 1 || $commission < 0 || $interest < 0 || $net < 0) {
                return [];
            }
            if ($definition['mode'] === 'fee') {
                if ($interest !== 0 || $commission !== $gross - $net || $commission < 1) {
                    return [];
                }
            } elseif ($net + $commission + $interest !== $gross) {
                return [];
            }
            $ready[] = [
                'key' => $key,
                'date' => $date,
                'gross_cents' => $gross,
                'commission_cents' => $commission,
                'interest_cents' => $interest,
                'net_cents' => $net,
                'document' => $document,
            ];
        }

        return $ready;
    }

    private function displayDate(string $date): string
    {
        $parts = explode('-', $date);

        return count($parts) === 3 ? $parts[2].'/'.$parts[1].'/'.$parts[0] : $date;
    }

    private function authorizeAccounting(): void
    {
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canViewAccounting()) {
            abort(403, 'No tiene permiso para ver la contabilidad.');
        }
    }
}
