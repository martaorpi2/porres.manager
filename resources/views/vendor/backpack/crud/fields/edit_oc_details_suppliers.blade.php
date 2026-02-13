@php
    $entry = $entry ?? $crud->getCurrentEntry();
    if (!$entry) {
        echo '<p class="text-muted">Guarde la orden primero para asignar proveedores por línea.</p>';
        return;
    }
    $entry->load('details.input', 'details.supplier');
    $details = $entry->details;
    $suppliers = \App\Models\Supplier::orderBy('company_name')->get();
@endphp
<div class="form-group col-sm-12">
    <label>Proveedor por línea</label>
    <p class="text-muted small">Puede cambiar el proveedor de cada ítem. Cada producto puede tener un proveedor distinto.</p>
    @if($details->isEmpty())
        <p class="text-muted">No hay detalles en esta orden. Agregue productos desde la solicitud de compra asociada o edite la orden.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio unit.</th>
                        <th>Proveedor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($details as $detail)
                        <tr>
                            <td>{{ $detail->input->name ?? '—' }}</td>
                            <td>{{ $detail->quantity }}</td>
                            <td>${{ number_format($detail->unit_price ?? 0, 2) }}</td>
                            <td>
                                <select name="detail_supplier[{{ $detail->id }}]" class="form-control form-control-sm">
                                    @foreach($suppliers as $sup)
                                        <option value="{{ $sup->id }}" {{ ($detail->supplier_id == $sup->id) ? 'selected' : '' }}>{{ $sup->company_name }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
