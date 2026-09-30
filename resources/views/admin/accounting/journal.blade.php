@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="mb-0 journal-title" bp-section="page-heading">Libro diario</h1>
    </section>
@endsection

@section('content')
<div class="row journal-page">
    <div class="col-12">
        <div class="card journal-card mb-3">
            <div class="card-body">
                <form method="get" action="{{ backpack_url('accounting-journal') }}" class="row g-2 align-items-end">
                    <div class="col-sm-6 col-md-2">
                        <label for="from" class="form-label journal-label">Desde</label>
                        <input type="date" name="from" id="from" class="form-control" value="{{ $filters['from'] }}">
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label for="to" class="form-label journal-label">Hasta</label>
                        <input type="date" name="to" id="to" class="form-control" value="{{ $filters['to'] }}">
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <label for="kind" class="form-label journal-label">Tipo</label>
                        <select name="kind" id="kind" class="form-control">
                            <option value="">Todos</option>
                            @foreach($kinds as $value => $label)
                                <option value="{{ $value }}" @selected($filters['kind'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <label for="account_id" class="form-label journal-label">Cuenta</label>
                        <select name="account_id" id="account_id" class="form-control">
                            <option value="">Todas</option>
                            @foreach($accounts as $id => $label)
                                <option value="{{ $id }}" @selected((int) $filters['account_id'] === (int) $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-12 col-md-2 d-flex gap-2 journal-actions">
                        <button type="submit" class="btn btn-primary journal-btn-filter">Filtrar</button>
                        <a href="{{ backpack_url('accounting-journal') }}" class="btn journal-btn-clear">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card journal-card journal-book">
            <div class="card-body p-0">
                @php
                    $accountQuery = array_filter([
                        'from' => request('from'),
                        'to' => request('to'),
                        'kind' => request('kind'),
                    ], fn ($value) => $value !== null && $value !== '');
                @endphp
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
                            @forelse($entries as $entry)
                                @php
                                    $debit = 0.0;
                                    $credit = 0.0;
                                @endphp
                                <tr class="journal-asiento-label">
                                    <td colspan="3">
                                        <strong>Asiento {{ $entry->entry_number }}</strong>
                                        <span class="journal-asiento-meta">{{ $entry->date?->format('d/m/Y') }} · {{ $entry->description }} · {{ $entry->kind_label }} ({{ $entry->status_label }})</span>
                                    </td>
                                    <td class="text-end">
                                        @if($entry->status === \App\Models\AccountingEntry::STATUS_POSTED)
                                            <a href="{{ backpack_url('accounting-journal/'.$entry->id.'/edit') }}?{{ http_build_query(array_filter(['from' => $filters['from'], 'to' => $filters['to'], 'kind' => $filters['kind'], 'account_id' => $filters['account_id']])) }}" class="btn btn-sm journal-btn-edit">Modificar</a>
                                        @endif
                                    </td>
                                </tr>
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
                                <tr class="journal-asiento-end">
                                    <td colspan="2">Totales</td>
                                    <td class="text-end">{{ number_format($debit, 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($credit, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-muted">No hay asientos para ese filtro.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('after_styles')
<style>
    .journal-title {
        color: #1e2a4a;
        font-weight: 600;
    }
    .journal-page .journal-card {
        background: #fff;
        border: 1px solid #e6e8ee;
        border-left: 1px solid #e6e8ee !important;
        border-radius: 6px;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
    }
    .journal-label {
        font-weight: 700;
        color: #1e2a4a;
        font-size: 0.95rem;
        margin-bottom: 0.35rem;
    }
    .journal-actions {
        gap: 0.5rem;
    }
    .journal-btn-filter {
        background-color: #871f1f;
        border-color: #871f1f;
        color: #fff;
        font-weight: 600;
        padding: 0.45rem 1.1rem;
    }
    .journal-btn-clear {
        background: #fff;
        border: 1px solid #d0d5dd;
        color: #344054 !important;
        font-weight: 600;
        padding: 0.45rem 1.1rem;
    }
    .journal-btn-clear:hover {
        background: #f8f9fb;
        color: #1e2a4a !important;
    }
    .journal-book {
        background: #fff;
        border-left: 4px solid #871f1f !important;
    }
    .journal-lines {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
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
        color: #1e2a4a;
    }
    .journal-asiento-label td {
        background: #f7f8fb !important;
        border-bottom: 1px solid #e6e8ee !important;
        padding-top: 0.85rem;
        padding-bottom: 0.85rem;
    }
    .journal-asiento-meta {
        margin-left: 0.75rem;
        font-weight: 400;
    }
    .journal-asiento-end td {
        font-weight: 700;
        border-bottom: 3px solid #871f1f !important;
    }
    .journal-account {
        color: #871f1f !important;
        font-weight: 600;
        text-decoration: none;
    }
    .journal-account:hover {
        text-decoration: underline;
    }
    .journal-btn-edit {
        background: #fff;
        border: 1px solid #871f1f;
        color: #871f1f !important;
        font-weight: 600;
        padding: 0.15rem 0.7rem;
    }
    .journal-btn-edit:hover {
        background: #871f1f;
        color: #fff !important;
    }
</style>
@endsection
