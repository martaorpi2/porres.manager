<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\MercadoPagoSalesImport;
use App\Services\QuotaAccountingService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class MercadoPagoSettlementController extends CrudController
{
    public function index()
    {
        $this->authorizeAccounting();

        return view('admin.accounting.mercadopago_import', $this->pageData());
    }

    public function preview(Request $request, MercadoPagoSalesImport $import)
    {
        $this->authorizeAccounting();

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ], [
            'archivo.required' => 'Elegí el Excel de ventas de Mercado Pago.',
            'archivo.mimes' => 'El archivo tiene que ser un Excel (.xlsx).',
            'archivo.max' => 'El Excel no puede superar los 10 MB.',
        ]);

        try {
            $preview = $import->preview($request->file('archivo')->getRealPath());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['archivo' => $exception->getMessage()]);
        } catch (Throwable) {
            return back()->withErrors(['archivo' => 'No se pudo leer el Excel de Mercado Pago.']);
        }

        if ($preview['rows'] === []) {
            return back()->withErrors(['archivo' => 'El Excel no tiene operaciones para registrar.']);
        }

        $token = (string) str()->uuid();
        $request->session()->put('mp_sales_import', [
            'token' => $token,
            'ready' => $preview['ready'],
        ]);

        return view('admin.accounting.mercadopago_import', array_merge($this->pageData(), [
            'rows' => $preview['rows'],
            'ready' => $preview['ready'],
            'token' => $token,
        ]));
    }

    public function store(Request $request, QuotaAccountingService $accounting)
    {
        $this->authorizeAccounting();

        $saved = $request->session()->get('mp_sales_import');
        $token = (string) $request->input('token');
        if (! is_array($saved) || $token === '' || ! hash_equals((string) ($saved['token'] ?? ''), $token)) {
            \Alert::warning('La vista previa venció. Volvé a subir el Excel.')->flash();

            return redirect(backpack_url('accounting-mercadopago'));
        }

        $ready = $this->readyRows($saved['ready'] ?? []);
        if ($ready === []) {
            \Alert::warning('No hay operaciones para registrar.')->flash();

            return redirect(backpack_url('accounting-mercadopago'));
        }

        try {
            $entries = $accounting->postImportedMercadoPagoSettlements($ready);
        } catch (Throwable $exception) {
            \Alert::error($exception->getMessage())->flash();

            return redirect(backpack_url('accounting-mercadopago'));
        }

        $request->session()->forget('mp_sales_import');

        if ($entries === []) {
            \Alert::warning('Esas operaciones ya tenían asiento de liquidación.')->flash();

            return redirect(backpack_url('accounting-mercadopago'));
        }

        $numbers = array_map(fn ($entry) => $entry->entry_number, $entries);
        $dates = array_map(fn ($entry) => $entry->date->toDateString(), $entries);
        sort($dates);

        \Alert::success('Se registraron los asientos '.implode(', ', $numbers).'.')->flash();

        return redirect(backpack_url('accounting-journal').'?'.http_build_query([
            'from' => $dates[0],
            'to' => $dates[array_key_last($dates)],
            'kind' => 'quota_mp_settlement',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function pageData(): array
    {
        return [
            'title' => 'Liquidación Mercado Pago',
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Libro diario' => backpack_url('accounting-journal'),
                'Mercado Pago' => false,
            ],
            'rows' => null,
            'ready' => [],
            'token' => null,
        ];
    }

    /**
     * @return list<array{date: string, eporres_order_id: int, gross_cents: int, commission_cents: int, net_cents: int}>
     */
    private function readyRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $ready = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                return [];
            }
            $date = (string) ($row['date'] ?? '');
            $orderId = (int) ($row['eporres_order_id'] ?? 0);
            $gross = (int) ($row['gross_cents'] ?? 0);
            $commission = (int) ($row['commission_cents'] ?? 0);
            $net = (int) ($row['net_cents'] ?? 0);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || $orderId < 1 || $gross < 1) {
                return [];
            }
            if (abs(($net + $commission) - $gross) > 2) {
                return [];
            }
            $ready[] = [
                'date' => $date,
                'eporres_order_id' => $orderId,
                'gross_cents' => $gross,
                'commission_cents' => $commission,
                'net_cents' => $net,
            ];
        }

        return $ready;
    }

    private function authorizeAccounting(): void
    {
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canViewAccounting()) {
            abort(403, 'No tiene permiso para ver la contabilidad.');
        }
    }
}
