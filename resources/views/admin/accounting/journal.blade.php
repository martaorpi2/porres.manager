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

        <div class="card journal-card">
            <div class="card-body table-responsive p-0">
                <table class="table journal-table mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Asiento</th>
                            <th>Descripción</th>
                            <th>Tipo</th>
                            <th class="text-end">Debe</th>
                            <th class="text-end">Haber</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            <tr>
                                <td class="text-nowrap">{{ $entry->date?->format('d/m/Y') }}</td>
                                <td class="text-nowrap">{{ $entry->entry_number }}</td>
                                <td>{{ $entry->description }}</td>
                                <td class="text-nowrap">{{ $entry->kind_label }}</td>
                                <td class="text-end text-nowrap">{{ number_format((float) $entry->debit_total, 2, ',', '.') }}</td>
                                <td class="text-end text-nowrap">{{ number_format((float) $entry->credit_total, 2, ',', '.') }}</td>
                                <td class="text-end">
                                    <a href="{{ backpack_url('accounting-journal/'.$entry->id) }}" class="journal-ver">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-muted">No hay asientos para ese filtro.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($entries->hasPages())
                <div class="card-footer journal-pagination">
                    {{ $entries->links('pagination::bootstrap-4') }}
                </div>
            @endif
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
        border: 1px solid #e6e8ee;
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
    .journal-table thead th {
        background: #f7f8fb;
        color: #1e2a4a;
        font-weight: 700;
        border-bottom: 1px solid #e6e8ee;
        text-transform: none;
        letter-spacing: 0;
        font-size: 0.95rem;
        padding: 0.85rem 1rem;
        vertical-align: middle;
    }
    .journal-table tbody td {
        padding: 0.85rem 1rem;
        border-top: 1px solid #eef0f3;
        color: #243044;
        vertical-align: middle;
        background: #fff;
    }
    .journal-table tbody tr:hover td {
        background: #fafbfc;
    }
    .journal-ver {
        color: #871f1f !important;
        font-weight: 600;
        text-decoration: none;
    }
    .journal-ver:hover {
        color: #a02a2a !important;
        text-decoration: underline;
    }
    .journal-pagination {
        background: #fff;
        border-top: 1px solid #e6e8ee;
        display: flex;
        justify-content: flex-end;
    }
    .journal-pagination .pagination {
        margin-bottom: 0;
    }
    .journal-pagination .page-link {
        color: #1e2a4a;
        border-color: #e6e8ee;
    }
    .journal-pagination .page-link:hover {
        color: #871f1f;
        background: #fdf6f6;
        border-color: #e6e8ee;
    }
    .journal-pagination .page-item.active .page-link {
        background-color: #871f1f;
        border-color: #871f1f;
        color: #fff;
    }
    .journal-pagination .page-item.disabled .page-link {
        color: #98a2b3;
        background: #fff;
        border-color: #e6e8ee;
    }
</style>
@endsection
