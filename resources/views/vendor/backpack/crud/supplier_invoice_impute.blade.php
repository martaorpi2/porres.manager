@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="text-capitalize mb-0" bp-section="page-heading">Imputar orden de pago</h1>
        <p class="ms-2 ml-2 mb-0" bp-section="page-subheading">Factura {{ $invoice->invoice_number }}</p>
        <p class="mb-0 ms-2 ml-2" bp-section="page-subheading-back-button">
            <small>
                <a href="{{ backpack_url('supplier-invoice/' . $invoice->id . '/show') }}" class="d-print-none font-sm">
                    <span><i class="la la-angle-double-left"></i> Volver a la factura</span>
                </a>
            </small>
        </p>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-10 col-lg-7">
        <div class="alert alert-info">
            <strong>OC:</strong> {{ $invoice->purchaseOrder?->number ?? '—' }} —
            <strong>Proveedor:</strong> {{ $invoice->supplier?->company_name ?? '—' }} —
            <strong>Saldo factura:</strong> ${{ number_format($invoice->openBalance(), 2) }} —
            <strong>Moneda:</strong> {{ $invoice->currency_code }}
        </div>
        <div class="card">
            <div class="card-header">
                <strong>Registrar imputación contable</strong>
                <br><small class="text-muted">El monto no puede superar el saldo de la factura ni el saldo imputable de la orden de pago elegida. Misma moneda y mismo proveedor que la OC.</small>
            </div>
            <div class="card-body">
                <form method="post" action="{{ backpack_url('supplier-invoice/' . $invoice->id . '/imputar') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="payment_order_id" class="form-label required">Orden de pago</label>
                        <select name="payment_order_id" id="payment_order_id" class="form-select @error('payment_order_id') is-invalid @enderror" required>
                            <option value="">— Seleccione —</option>
                            @foreach ($paymentOrderOptions as $id => $label)
                                <option value="{{ $id }}" @selected(old('payment_order_id') == $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('payment_order_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label required">Monto a imputar</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
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
                        <a href="{{ backpack_url('supplier-invoice/' . $invoice->id . '/show') }}" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
