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
                round((float) $plan['paid_plan'], 2),
                round((float) $plan['pure_plan'], 2),
                round((float) $plan['late_plan'], 2),
                round((float) $plan['financing_plan'], 2),
                round((float) $plan['method_plan'], 2),
                round((float) $plan['impute_pure'], 2),
                round((float) $plan['impute_capitalized'], 2),
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
            round($paid, 2),
            round($pure, 2),
            round($late, 2),
            round($financing, 2),
            round($method, 2),
            round($imputePure, 2),
            round($imputeCapitalized, 2),
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

    private function authorizeAccounting(): void
    {
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canViewAccounting()) {
            abort(403, 'No tiene permiso para ver la contabilidad.');
        }
    }
}
