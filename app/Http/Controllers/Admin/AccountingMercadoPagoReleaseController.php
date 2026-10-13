<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\EporresMercadoPagoReleaseReport;
use Illuminate\Http\Request;

class AccountingMercadoPagoReleaseController
{
    public function index(Request $request, EporresMercadoPagoReleaseReport $report)
    {
        $this->authorizeAccounting();

        return view('admin.accounting.mercadopago_releases', $report->build($request));
    }

    private function authorizeAccounting(): void
    {
        $user = backpack_user();
        if (! $user instanceof User || ! $user->canViewAccounting()) {
            abort(403, 'No tiene permiso para ver la contabilidad.');
        }
    }
}
