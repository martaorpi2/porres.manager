<label class="form-label">{!! $field['label'] !!}</label>
@if (! empty($field['hint']))
    <p class="help-block text-muted small mb-2">{!! $field['hint'] !!}</p>
@endif
@php
    $rows = old('imputations');
    if (! is_array($rows)) {
        $rows = $field['rows'] ?? [];
    }
    $accountOptions = $field['account_options'] ?? [];
    $impErrors = collect($errors->getMessages())->filter(fn ($msgs, $k) => str_starts_with((string) $k, 'imputations'));
@endphp
@if ($impErrors->isNotEmpty())
    <div class="alert alert-danger py-2 small">
        @foreach ($impErrors as $msgs)
            @foreach ((array) $msgs as $msg)
                <div>{{ $msg }}</div>
            @endforeach
        @endforeach
    </div>
@endif
<div class="table-responsive border rounded p-2">
    <table class="table table-sm align-middle mb-2" id="fm-imp-table">
        <thead>
            <tr>
                <th>Cuenta</th>
                <th style="width:18%">Monto</th>
                <th style="width:28%">Detalle</th>
                <th style="width:8%"></th>
            </tr>
        </thead>
        <tbody id="fm-imp-body">
            @foreach ($rows as $i => $row)
                @php $row = is_array($row) ? $row : []; @endphp
                <tr class="fm-imp-row">
                    <td>
                        <select name="imputations[{{ $i }}][accounting_account_id]" class="form-select form-select-sm" required>
                            <option value="">— Seleccione —</option>
                            @foreach ($accountOptions as $id => $label)
                                <option value="{{ $id }}" @selected((string) ($row['accounting_account_id'] ?? '') === (string) $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0.01" name="imputations[{{ $i }}][amount]" value="{{ e($row['amount'] ?? '') }}" class="form-control form-control-sm" required>
                    </td>
                    <td>
                        <input type="text" name="imputations[{{ $i }}][memo]" value="{{ e($row['memo'] ?? '') }}" class="form-control form-control-sm" placeholder="Opcional">
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-danger fm-imp-remove">&times;</button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <button type="button" class="btn btn-sm btn-outline-primary" id="fm-imp-add">
        <i class="la la-plus"></i> Agregar imputación
    </button>
</div>
@push('after_scripts')
<script>
(function () {
    var tbody = document.getElementById('fm-imp-body');
    var addBtn = document.getElementById('fm-imp-add');
    if (!tbody || !addBtn) return;
    var opts = @json($accountOptions);
    var nextIndex = tbody.querySelectorAll('tr.fm-imp-row').length;
    function optionsHtml(selected) {
        var html = '<option value="">— Seleccione —</option>';
        Object.keys(opts).forEach(function (id) {
            var label = String(opts[id]).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            html += '<option value="' + id + '"' + (String(selected) === String(id) ? ' selected' : '') + '>' + label + '</option>';
        });
        return html;
    }
    function rowHtml(i) {
        return '<tr class="fm-imp-row"><td><select name="imputations[' + i + '][accounting_account_id]" class="form-select form-select-sm" required>' + optionsHtml('') + '</select></td>'
            + '<td><input type="number" step="0.01" min="0.01" name="imputations[' + i + '][amount]" class="form-control form-control-sm" required></td>'
            + '<td><input type="text" name="imputations[' + i + '][memo]" class="form-control form-control-sm" placeholder="Opcional"></td>'
            + '<td><button type="button" class="btn btn-sm btn-outline-danger fm-imp-remove">&times;</button></td></tr>';
    }
    addBtn.addEventListener('click', function () {
        tbody.insertAdjacentHTML('beforeend', rowHtml(nextIndex++));
    });
    tbody.addEventListener('click', function (e) {
        if (e.target.closest('.fm-imp-remove')) {
            var tr = e.target.closest('tr');
            if (tr) tr.remove();
        }
    });
})();
</script>
@endpush
