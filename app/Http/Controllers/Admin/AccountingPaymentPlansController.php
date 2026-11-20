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
        $paid = 0.0;
        $pure = 0.0;
        $late = 0.0;
        $financing = 0.0;
        $method = 0.0;
        $imputePure = 0.0;
        $imputeCapitalized = 0.0;
        foreach ($data['plans'] as $plan) {
            $rows[] = [
                $plan['student'],
                $plan['paid'].' / '.$plan['dues'],
                $plan['opened_on'],
                $this->money($plan['paid_plan']),
                $this->money($plan['pure_plan']),
                $this->money($plan['late_plan']),
                $this->money($plan['financing_plan']),
                $this->money($plan['method_plan']),
                $this->money($plan['impute_pure']),
                $this->money($plan['impute_capitalized']),
            ];
            $paid += (float) $plan['paid_plan'];
            $pure += (float) $plan['pure_plan'];
            $late += (float) $plan['late_plan'];
            $financing += (float) $plan['financing_plan'];
            $method += (float) $plan['method_plan'];
            $imputePure += (float) $plan['impute_pure'];
            $imputeCapitalized += (float) $plan['impute_capitalized'];
        }
        $rows[] = [
            'TOTAL',
            '',
            '',
            $this->money($paid),
            $this->money($pure),
            $this->money($late),
            $this->money($financing),
            $this->money($method),
            $this->money($imputePure),
            $this->money($imputeCapitalized),
        ];

        return SpreadsheetDownload::fromRows(
            'planes_de_pago_'.date('Y-m-d').'.xlsx',
            [
                'Alumno', 'Cuotas', 'Alta', 'Importe pagado del plan', 'Cuota pura del plan',
                'Interés por mora del plan', 'Interés de financiación', 'Interés del medio de pago',
                'Imputación cuota pura', 'Imputación interés capitalizado',
            ],
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
