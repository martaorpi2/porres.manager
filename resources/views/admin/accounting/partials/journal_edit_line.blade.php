<tr>
    <td>
        <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line['id'] ?? '' }}">
        <select name="lines[{{ $index }}][accounting_account_id]" class="form-control" required>
            <option value="">Elegir cuenta</option>
            @foreach($accounts as $id => $label)
                <option value="{{ $id }}" @selected((int) ($line['accounting_account_id'] ?? 0) === (int) $id)>{{ $label }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="text" name="lines[{{ $index }}][debit]" class="form-control text-end line-debit" inputmode="decimal" value="{{ $line['debit'] ?? '0,00' }}">
    </td>
    <td>
        <input type="text" name="lines[{{ $index }}][credit]" class="form-control text-end line-credit" inputmode="decimal" value="{{ $line['credit'] ?? '0,00' }}">
    </td>
    <td class="text-end">
        <button type="button" class="btn btn-sm journal-btn-remove">Quitar</button>
    </td>
</tr>
