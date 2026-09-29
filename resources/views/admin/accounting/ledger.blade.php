@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="mb-0" bp-section="page-heading">Sumas y saldos</h1>
    </section>
@endsection

@section('content')
@php
    $totalDebit = 0.0;
    $totalCredit = 0.0;
@endphp
<div class="row">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body">
                <form method="get" action="{{ backpack_url('accounting-ledger') }}" class="row g-2 align-items-end">
                    <div class="col-sm-4 col-md-2">
                        <label for="from" class="form-label">Desde</label>
                        <input type="date" name="from" id="from" class="form-control" value="{{ $filters['from'] }}">
                    </div>
                    <div class="col-sm-4 col-md-2">
                        <label for="to" class="form-label">Hasta</label>
                        <input type="date" name="to" id="to" class="form-control" value="{{ $filters['to'] }}">
                    </div>
                    <div class="col-sm-4 col-md-3">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="{{ backpack_url('accounting-ledger') }}" class="btn btn-secondary">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body table-responsive p-0">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Cuenta</th>
                            <th class="text-end">Debe</th>
                            <th class="text-end">Haber</th>
                            <th class="text-end">Saldo deudor</th>
                            <th class="text-end">Saldo acreedor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                $debit = (float) $row->debit;
                                $credit = (float) $row->credit;
                                $balance = round($debit - $credit, 2);
                                $totalDebit += $debit;
                                $totalCredit += $credit;
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ backpack_url('accounting-journal') }}?account_id={{ $row->id }}{{ $filters['from'] ? '&from='.$filters['from'] : '' }}{{ $filters['to'] ? '&to='.$filters['to'] : '' }}">{{ $row->code }}</a>
                                </td>
                                <td>{{ $row->name }}</td>
                                <td class="text-end">{{ number_format($debit, 2, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($credit, 2, ',', '.') }}</td>
                                <td class="text-end">{{ $balance > 0 ? number_format($balance, 2, ',', '.') : '' }}</td>
                                <td class="text-end">{{ $balance < 0 ? number_format(abs($balance), 2, ',', '.') : '' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-muted">Todavía no hay movimientos contabilizados en ese período.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($rows->isNotEmpty())
                        <tfoot>
                            <tr class="fw-bold font-weight-bold">
                                <td colspan="2">Totales</td>
                                <td class="text-end">{{ number_format($totalDebit, 2, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($totalCredit, 2, ',', '.') }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
