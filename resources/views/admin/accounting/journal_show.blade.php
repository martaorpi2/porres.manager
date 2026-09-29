@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="mb-0" bp-section="page-heading">Asiento {{ $entry->entry_number }}</h1>
        <a href="{{ backpack_url('accounting-journal') }}" class="btn btn-sm btn-secondary ms-3 ml-3">Volver al diario</a>
    </section>
@endsection

@section('content')
@php
    $debit = 0.0;
    $credit = 0.0;
@endphp
<div class="row">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-body">
                <p class="mb-1"><strong>Fecha:</strong> {{ $entry->date?->format('d/m/Y') }}</p>
                <p class="mb-1"><strong>Descripción:</strong> {{ $entry->description }}</p>
                <p class="mb-3"><strong>Tipo:</strong> {{ $entry->kind_label }} <span class="text-muted">({{ $entry->status_label }})</span></p>

                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr style="background:#871f1f;color:#fff;">
                                <th>Cuenta</th>
                                <th>Nombre</th>
                                <th class="text-end">Debe</th>
                                <th class="text-end">Haber</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($entry->lines as $line)
                                @php
                                    $debit += (float) $line->debit;
                                    $credit += (float) $line->credit;
                                @endphp
                                <tr>
                                    <td>
                                        @if($line->account)
                                            <a href="{{ backpack_url('accounting-journal') }}?account_id={{ $line->account->id }}">{{ $line->account->code }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $line->account?->name ?? '—' }}</td>
                                    <td class="text-end">{{ number_format((float) $line->debit, 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format((float) $line->credit, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold font-weight-bold">
                                <td colspan="2">Totales</td>
                                <td class="text-end">{{ number_format($debit, 2, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($credit, 2, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
