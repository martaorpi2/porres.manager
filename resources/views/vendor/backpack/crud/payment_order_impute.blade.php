@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="text-capitalize mb-0" bp-section="page-heading">Imputar a factura</h1>
        <p class="ms-2 ml-2 mb-0" bp-section="page-subheading">Orden de pago {{ $paymentOrder->payment_number }}</p>
        <p class="mb-0 ms-2 ml-2" bp-section="page-subheading-back-button">
            <small>
                <a href="{{ backpack_url('payment-order/' . $paymentOrder->id . '/show') }}" class="d-print-none font-sm">
                    <span><i class="la la-angle-double-left"></i> Volver a la orden de pago</span>
                </a>
            </small>
        </p>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-10 col-lg-7">
        <div class="alert alert-info" id="op-remaining" data-remaining="{{ $remaining }}">
            <strong>OP:</strong> {{ $paymentOrder->payment_number }} —
            <strong>Tipo:</strong> {{ $paymentOrder->billing_kind === 'anticipo' ? 'Anticipo' : 'Normal' }} —
            <strong>Saldo imputable:</strong> ${{ number_format($remaining, 2) }} —
            <strong>Moneda:</strong> {{ strtoupper(trim((string) ($paymentOrder->currency_code ?? ''))) !== '' ? strtoupper(trim((string) $paymentOrder->currency_code)) : 'ARS' }}
            @if ($paymentOrder->purchase_order)
                <br><strong>OC:</strong> {{ $paymentOrder->purchase_order->number }}
            @endif
        </div>
        <div class="card">
            <div class="card-header">
                <strong>Registrar imputación contable</strong>
                <br><small class="text-muted">El monto no puede superar el saldo de la factura elegida ni el saldo imputable de esta orden de pago. Misma moneda y mismo proveedor que la OC. Puede ser el total o una parte; lo no cubierto queda como saldo de la factura.</small>
            </div>
            <div class="card-body">
                <form method="post" action="{{ backpack_url('payment-order/' . $paymentOrder->id . '/imputar') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="supplier_invoice_id" class="form-label required">Factura</label>
                        <select name="supplier_invoice_id" id="supplier_invoice_id" class="form-select @error('supplier_invoice_id') is-invalid @enderror" required>
                            <option value="">— Seleccione —</option>
                            @foreach ($invoiceOptions as $id => $opt)
                                <option value="{{ $id }}" data-saldo="{{ $opt['saldo'] }}" @selected(old('supplier_invoice_id') == $id)>{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                        @error('supplier_invoice_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label required">Monto a imputar</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="form-text text-muted">Al elegir factura se propone el menor entre el saldo de la factura y el disponible de la OP. Puede bajarlo para un pago parcial.</small>
                    </div>
                    <div class="mb-3">
                        <label for="imputed_at" class="form-label required">Fecha de imputación</label>
                        <input type="date" name="imputed_at" id="imputed_at" class="form-control @error('imputed_at') is-invalid @enderror" value="{{ old('imputed_at', now()->toDateString()) }}" required>
                        @error('imputed_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="la la-save"></i> Guardar imputación
                        </button>
                        <a href="{{ backpack_url('payment-order/' . $paymentOrder->id . '/show') }}" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var select = document.getElementById('supplier_invoice_id');
    var amount = document.getElementById('amount');
    var capEl = document.getElementById('op-remaining');
    if (!select || !amount || !capEl) {
        return;
    }
    var cap = parseFloat(capEl.getAttribute('data-remaining') || '0');
    select.addEventListener('change', function () {
        var opt = this.options[this.selectedIndex];
        var saldo = parseFloat(opt.getAttribute('data-saldo') || '0');
        if (!opt.value || !(saldo > 0) || !(cap > 0)) {
            return;
        }
        amount.value = Math.min(saldo, cap).toFixed(2);
    });
})();
</script>
@endsection
