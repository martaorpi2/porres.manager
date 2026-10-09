@once
<style>
    .journal-entry-block {
        margin-bottom: 1.25rem;
    }
    .journal-entry-heading {
        color: #1e2a4a;
        font-weight: 600;
        font-size: 1.35rem;
        margin-bottom: 0.6rem;
    }
    .journal-entry-card {
        background: #fff;
        border: 1px solid #e6e8ee;
        border-left: 4px solid #871f1f !important;
        border-radius: 4px;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
    }
    .journal-entry-card .card-body {
        color: #1e2a4a;
    }
    .journal-lines {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    .journal-lines thead th {
        background: #871f1f !important;
        color: #fff !important;
        font-weight: 700;
        border: none;
        text-transform: none;
        letter-spacing: 0;
        padding: 0.7rem 0.9rem;
    }
    .journal-lines tbody td {
        padding: 0.7rem 0.9rem;
        border-bottom: 1px solid #e6e8ee;
        background: #fff;
        vertical-align: middle;
    }
    .journal-lines tfoot td {
        padding: 0.75rem 0.9rem;
        font-weight: 700;
        color: #1e2a4a;
        border-top: none;
        background: #fff;
    }
    .journal-account {
        color: #871f1f !important;
        font-weight: 600;
        text-decoration: none;
    }
    .journal-account:hover {
        text-decoration: underline;
    }
</style>
@endonce
@php
    $debit = 0.0;
    $credit = 0.0;
    $accountQuery = array_filter([
        'from' => request('from'),
        'to' => request('to'),
        'kind' => request('kind'),
    ], fn ($value) => $value !== null && $value !== '');
@endphp
<div class="journal-entry-block">
    @if($heading ?? true)
        <h2 class="journal-entry-heading">Asiento {{ $entry->entry_number }}</h2>
    @endif
    <div class="card journal-entry-card">
        <div class="card-body">
            <p class="mb-1"><strong>Fecha:</strong> {{ $entry->date?->format('d/m/Y') }}</p>
            <p class="mb-1"><strong>Descripción:</strong> {{ $entry->description }}</p>
            <p class="mb-3"><strong>Tipo:</strong> {{ $entry->kind_label }}</p>

            <div class="table-responsive">
                <table class="table journal-lines mb-0">
                    <thead>
                        <tr>
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
                                        <a href="{{ backpack_url('accounting-journal') }}?{{ http_build_query($accountQuery + ['account_id' => $line->account->id]) }}" class="journal-account">{{ $line->account->code }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $line->account?->name ?? '—' }}</td>
                                <td class="text-end text-nowrap">{{ number_format((float) $line->debit, 2, ',', '.') }}</td>
                                <td class="text-end text-nowrap">{{ number_format((float) $line->credit, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
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
