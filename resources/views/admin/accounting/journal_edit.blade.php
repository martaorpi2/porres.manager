@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-center d-print-none" bp-section="page-header">
        <h1 class="mb-0 journal-title" bp-section="page-heading">Modificar {{ $entry->entry_number }}</h1>
        <a href="{{ backpack_url('accounting-journal') }}?{{ http_build_query($returnQuery) }}" class="btn journal-back ms-3 ml-3">Volver al diario</a>
    </section>
@endsection

@section('content')
@php
    $formLines = old('lines');
    if (! is_array($formLines)) {
        $formLines = $entry->lines->map(fn ($line) => [
            'id' => $line->id,
            'accounting_account_id' => $line->accounting_account_id,
            'debit' => number_format((float) $line->debit, 2, ',', '.'),
            'credit' => number_format((float) $line->credit, 2, ',', '.'),
        ])->all();
    }
@endphp
<div class="row">
    <div class="col-12">
        <div class="card journal-edit-card">
            <div class="card-body">
                <p class="mb-1"><strong>Fecha:</strong> {{ $entry->date?->format('d/m/Y') }}</p>
                <p class="mb-3"><strong>Tipo:</strong> {{ $entry->kind_label }}</p>

                @if($errors->has('lines'))
                    <div class="alert alert-danger" role="alert">{{ $errors->first('lines') }}</div>
                @endif
                <div id="save-warning" class="alert alert-danger" role="alert" hidden></div>

                <form method="post" action="{{ backpack_url('accounting-journal/'.$entry->id) }}" id="entry-form">
                    @csrf
                    @method('PUT')
                    @foreach($returnQuery as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach

                    <div class="mb-3">
                        <label for="entry-description" class="journal-label">Descripción</label>
                        <input type="text" name="description" id="entry-description" class="form-control @error('description') is-invalid @enderror" maxlength="255" required value="{{ old('description', $entry->description) }}">
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="table-responsive">
                        <table class="table journal-edit-lines mb-2" id="lines-table">
                            <thead>
                                <tr>
                                    <th>Cuenta</th>
                                    <th class="text-end journal-amount">Debe</th>
                                    <th class="text-end journal-amount">Haber</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="lines-body">
                                @foreach($formLines as $index => $line)
                                    @include('admin.accounting.partials.journal_edit_line', ['index' => $index, 'line' => $line, 'accounts' => $accounts])
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Totales</td>
                                    <td class="text-end" id="total-debit">0,00</td>
                                    <td class="text-end" id="total-credit">0,00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="4" id="balance-note" class="journal-balance"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="d-flex gap-2 journal-edit-actions">
                        <button type="button" class="btn journal-btn-add" id="add-line">Agregar cuenta</button>
                        <button type="submit" class="btn journal-btn-save">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<template id="line-template">
    @include('admin.accounting.partials.journal_edit_line', ['index' => '__INDEX__', 'line' => ['id' => '', 'accounting_account_id' => '', 'debit' => '0,00', 'credit' => '0,00'], 'accounts' => $accounts])
</template>
@endsection

@section('after_styles')
<style>
    .journal-title { color: #1e2a4a; font-weight: 600; }
    .journal-back {
        background: #fff;
        border: 1px solid #e7c4c4;
        color: #5c2a32 !important;
        font-size: 0.85rem;
        font-weight: 600;
        padding: 0.2rem 0.65rem;
        border-radius: 6px;
    }
    .journal-edit-card {
        background: #fff;
        border: 1px solid #e6e8ee;
        border-left: 4px solid #871f1f !important;
        border-radius: 6px;
    }
    .journal-edit-card .card-body { color: #1e2a4a; }
    .journal-label {
        font-weight: 700;
        color: #1e2a4a;
        font-size: 0.95rem;
        margin-bottom: 0.35rem;
    }
    .journal-edit-lines thead th {
        background: #871f1f !important;
        color: #fff !important;
        font-weight: 700;
        border: none;
        padding: 0.7rem 0.9rem;
    }
    .journal-edit-lines td { vertical-align: middle; }
    .journal-edit-lines tfoot td { font-weight: 700; }
    .journal-amount { width: 9rem; }
    .journal-edit-lines select,
    .journal-edit-lines input {
        color: #1e2a4a;
    }
    .journal-edit-actions { gap: 0.5rem; }
    .journal-btn-add,
    .journal-btn-remove {
        background: #fff;
        border: 1px solid #871f1f;
        color: #871f1f !important;
        font-weight: 600;
    }
    .journal-btn-add:hover,
    .journal-btn-remove:hover {
        background: #fdf6f6;
    }
    .journal-btn-save {
        background: #871f1f;
        border-color: #871f1f;
        color: #fff;
        font-weight: 600;
    }
    .journal-balance.is-ok { color: #1e2a4a; font-weight: 600; }
    .journal-balance.is-diff { color: #871f1f; font-weight: 700; }
</style>
@endsection

@section('after_scripts')
<script>
    (function () {
        var body = document.getElementById('lines-body');
        var template = document.getElementById('line-template');
        var nextIndex = body.querySelectorAll('tr').length;

        function parseAmount(value) {
            var text = String(value || '').trim().replace(/\s/g, '');
            if (text === '') return 0;
            if (text.indexOf(',') !== -1 && text.indexOf('.') !== -1) {
                text = text.replace(/\./g, '');
            }
            text = text.replace(',', '.');
            var number = Number(text);
            return Number.isFinite(number) ? number : 0;
        }

        function formatAmount(number) {
            return number.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function refreshTotals() {
            var debit = 0;
            var credit = 0;
            var rows = body.querySelectorAll('tr');
            rows.forEach(function (row) {
                debit += parseAmount(row.querySelector('.line-debit').value);
                credit += parseAmount(row.querySelector('.line-credit').value);
                row.querySelector('.journal-btn-remove').disabled = rows.length < 3;
            });
            document.getElementById('total-debit').textContent = formatAmount(debit);
            document.getElementById('total-credit').textContent = formatAmount(credit);
            var note = document.getElementById('balance-note');
            var diff = Math.round((debit - credit) * 100) / 100;
            if (diff === 0) {
                note.textContent = 'El debe y el haber coinciden.';
                note.className = 'journal-balance is-ok';
            } else {
                note.textContent = 'Diferencia: ' + formatAmount(Math.abs(diff)) + '. El debe y el haber tienen que ser iguales para guardar.';
                note.className = 'journal-balance is-diff';
            }
        }

        body.addEventListener('input', refreshTotals);
        body.addEventListener('click', function (event) {
            var button = event.target.closest('.journal-btn-remove');
            if (!button) return;
            var row = button.closest('tr');
            if (body.querySelectorAll('tr').length < 3) return;
            row.remove();
            refreshTotals();
        });

        document.getElementById('add-line').addEventListener('click', function () {
            var html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
            nextIndex += 1;
            body.insertAdjacentHTML('beforeend', html);
            refreshTotals();
        });

        document.getElementById('entry-form').addEventListener('submit', function (event) {
            var debit = 0;
            var credit = 0;
            body.querySelectorAll('tr').forEach(function (row) {
                debit += Math.round(parseAmount(row.querySelector('.line-debit').value) * 100);
                credit += Math.round(parseAmount(row.querySelector('.line-credit').value) * 100);
            });
            if (debit === credit) {
                return;
            }
            event.preventDefault();
            var warning = document.getElementById('save-warning');
            warning.hidden = false;
            warning.textContent = 'El debe (' + formatAmount(debit / 100) + ') y el haber (' + formatAmount(credit / 100) + ') no coinciden. No se guardaron los cambios.';
            warning.scrollIntoView({ block: 'center' });
        });

        body.addEventListener('input', function () {
            document.getElementById('save-warning').hidden = true;
        });

        refreshTotals();
    })();
</script>
@endsection
