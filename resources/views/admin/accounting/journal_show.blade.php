@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-center d-print-none" bp-section="page-header">
        <h1 class="mb-0 journal-title" bp-section="page-heading">Asiento {{ $entry->entry_number }}</h1>
        <a href="{{ backpack_url('accounting-journal') }}" class="btn journal-back ms-3 ml-3">Volver al diario</a>
        @if($entry->status === \App\Models\AccountingEntry::STATUS_POSTED)
            <a href="{{ backpack_url('accounting-journal/'.$entry->id.'/edit') }}" class="btn journal-btn-edit ms-2 ml-2">Modificar</a>
        @endif
    </section>
@endsection

@section('content')
<div class="row journal-show">
    <div class="col-12">
        @include('admin.accounting.partials.journal_entry', ['entry' => $entry, 'heading' => false])
    </div>
</div>
@endsection

@section('after_styles')
<style>
    .journal-title {
        color: #1e2a4a;
        font-weight: 600;
    }
    .journal-back {
        background: #fff;
        border: 1px solid #e7c4c4;
        color: #5c2a32 !important;
        font-size: 0.85rem;
        font-weight: 600;
        padding: 0.2rem 0.65rem;
        border-radius: 6px;
        line-height: 1.4;
    }
    .journal-back:hover {
        background: #fdf6f6;
        color: #871f1f !important;
    }
    .journal-btn-edit {
        background: #871f1f;
        border: 1px solid #871f1f;
        color: #fff !important;
        font-size: 0.85rem;
        font-weight: 600;
        padding: 0.2rem 0.65rem;
        border-radius: 6px;
        line-height: 1.4;
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
@endsection
