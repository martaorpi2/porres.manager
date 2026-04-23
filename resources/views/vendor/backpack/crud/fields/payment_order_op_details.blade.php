{{-- Detalle de pagos (reemplazo de repeatable PRO): mismos nombres que espera PaymentOrderRequest / syncOpDetailsFromRequest --}}
<label class="form-label">{!! $field['label'] !!}</label>
@if (! empty($field['hint']))
    <p class="help-block text-muted small mb-2">{!! $field['hint'] !!}</p>
@endif

@php
    $rows = old('payment_details');
    if (! is_array($rows)) {
        $rows = $field['rows'] ?? [];
    }
@endphp

@php
    $pdErrors = collect($errors->getMessages())->filter(fn ($msgs, $k) => str_starts_with((string) $k, 'payment_details'));
@endphp
@if ($pdErrors->isNotEmpty())
    <div class="alert alert-danger py-2 small">
        @foreach ($pdErrors as $msgs)
            @foreach ((array) $msgs as $msg)
                <div>{{ $msg }}</div>
            @endforeach
        @endforeach
    </div>
@endif

<div class="table-responsive border rounded p-2 bg-body-secondary">
    <table class="table table-sm align-middle mb-2" id="op-details-table">
        <thead>
            <tr>
                <th style="width:18%">Concepto</th>
                <th style="width:14%">Monto</th>
                <th style="width:22%">Método (línea)</th>
                <th style="width:18%">Vencimiento</th>
                <th style="width:18%">Pagado el</th>
                <th style="width:10%"></th>
            </tr>
        </thead>
        <tbody id="op-details-body">
            @foreach ($rows as $i => $row)
                @php
                    $row = is_array($row) ? $row : [];
                    $concept = $row['concept'] ?? 'partiality';
                    $amount = $row['amount'] ?? '';
                    $method = $row['method_payment'] ?? '';
                    $exp = $row['expiration_date'] ?? '';
                    $paid = $row['actual_payment_date'] ?? '';
                @endphp
                <tr class="op-detail-row">
                    <td>
                        <select name="payment_details[{{ $i }}][concept]" class="form-select form-select-sm" required>
                            <option value="advance" @selected($concept === 'advance')>Anticipo</option>
                            <option value="partiality" @selected($concept === 'partiality')>Parcialidad</option>
                            <option value="residue" @selected($concept === 'residue')>Saldo</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" name="payment_details[{{ $i }}][amount]" value="{{ e($amount) }}" class="form-control form-control-sm" required>
                    </td>
                    <td>
                        <input type="text" name="payment_details[{{ $i }}][method_payment]" value="{{ e($method) }}" class="form-control form-control-sm" placeholder="Opcional">
                    </td>
                    <td>
                        <input type="date" name="payment_details[{{ $i }}][expiration_date]" value="{{ e($exp) }}" class="form-control form-control-sm">
                    </td>
                    <td>
                        <input type="date" name="payment_details[{{ $i }}][actual_payment_date]" value="{{ e($paid) }}" class="form-control form-control-sm">
                    </td>
                    <td class="text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-danger op-detail-remove" title="Quitar línea">&times;</button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <button type="button" class="btn btn-sm btn-outline-primary" id="op-detail-add-row">
        <i class="la la-plus"></i> Agregar línea de pago
    </button>
</div>

@push('after_scripts')
<script>
    (function () {
        const tbody = document.getElementById('op-details-body');
        const addBtn = document.getElementById('op-detail-add-row');
        if (!tbody || !addBtn) return;

        let nextIndex = tbody.querySelectorAll('tr.op-detail-row').length;

        function rowHtml(i) {
            return (
                '<tr class="op-detail-row">' +
                '<td><select name="payment_details[' + i + '][concept]" class="form-select form-select-sm" required>' +
                '<option value="advance">Anticipo</option>' +
                '<option value="partiality" selected>Parcialidad</option>' +
                '<option value="residue">Saldo</option>' +
                '</select></td>' +
                '<td><input type="number" step="0.01" min="0" name="payment_details[' + i + '][amount]" class="form-control form-control-sm" required></td>' +
                '<td><input type="text" name="payment_details[' + i + '][method_payment]" class="form-control form-control-sm" placeholder="Opcional"></td>' +
                '<td><input type="date" name="payment_details[' + i + '][expiration_date]" class="form-control form-control-sm"></td>' +
                '<td><input type="date" name="payment_details[' + i + '][actual_payment_date]" class="form-control form-control-sm"></td>' +
                '<td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-danger op-detail-remove" title="Quitar línea">&times;</button></td>' +
                '</tr>'
            );
        }

        addBtn.addEventListener('click', function () {
            tbody.insertAdjacentHTML('beforeend', rowHtml(nextIndex));
            nextIndex += 1;
        });

        tbody.addEventListener('click', function (e) {
            const btn = e.target.closest('.op-detail-remove');
            if (!btn) return;
            const tr = btn.closest('tr.op-detail-row');
            if (tr) tr.remove();
        });
    })();
</script>
@endpush
