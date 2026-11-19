<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\PaymentPlanPortfolio;
use App\Support\SpreadsheetDownload;

class AccountingPaymentPlansController
{
    public function index(PaymentPlanPortfolio $report)
    {
        $this->authorizeAccounting();

        return view('admin.accounting.payment_plans', $report->report());
    }

    public function excel(PaymentPlanPortfolio $report)
    {
        $this->authorizeAccounting();
        $data = $report->report();
        $rows = [];
        foreach ($data['plans'] as $plan) {
            $rows[] = [
                $plan['id'],
                $plan['student'],
                $plan['opened_on'],
                $plan['paid'],
                $plan['dues'],
                $this->money($plan['debt']),
                $this->money($plan['late']),
                $this->money($plan['financing']),
                $this->money($plan['pending']),
            ];
        }
        $rows[] = [
            'TOTAL',
            '',
            '',
            $data['paid_installments'],
            $data['installments'],
            $this->money($data['debt']),
            $this->money($data['late']),
            $this->money($data['financing']),
            $this->money($data['pending']),
        ];

        return SpreadsheetDownload::fromRows(
            'planes_de_pago_'.date('Y-m-d').'.xlsx',
            ['Plan', 'Alumno', 'Alta', 'Cuotas pagadas', 'Cuotas del plan', 'Cuota pura', 'Mora', 'Financiación', 'Saldo'],
            $rows
        );
    }

    private function money(float $amount): string
    {
        return '$'.number_format($amount, 2, ',', '.');
    }

    private function authorizeAccounting(): void
    {
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canViewAccounting()) {
            abort(403, 'No tiene permiso para ver la contabilidad.');
        }
    }
}
