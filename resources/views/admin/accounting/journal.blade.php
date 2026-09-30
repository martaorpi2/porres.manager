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

        @forelse($entries as $entry)
            @include('admin.accounting.partials.journal_entry', ['entry' => $entry])
        @empty
            <div class="card journal-card">
                <div class="card-body text-muted">No hay asientos para ese filtro.</div>
            </div>
        @endforelse
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
</style>
@endsection
