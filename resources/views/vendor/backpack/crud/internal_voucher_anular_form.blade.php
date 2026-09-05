@extends(backpack_view('blank'))

@php
    $defaultBreadcrumbs = [
        trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
        $crud->entity_name_plural => url($crud->route),
        $voucher->number => backpack_url('internal-voucher/' . $voucher->id . '/show'),
        'Anular' => false,
    ];
    $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
@endphp

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="text-capitalize mb-0" bp-section="page-heading">Anular comprobante interno</h1>
        <p class="ms-2 ml-2 mb-0" bp-section="page-subheading">{{ $voucher->number }}</p>
        <p class="mb-0 ms-2 ml-2" bp-section="page-subheading-back-button">
            <small>
                <a href="{{ backpack_url('internal-voucher/' . $voucher->id . '/show') }}" class="d-print-none font-sm">
                    <span><i class="la la-angle-double-left"></i> Volver al comprobante</span>
                </a>
            </small>
        </p>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-header">
                <strong>{{ $voucher->document_title }} {{ $voucher->number }}</strong>
                <br><small class="text-muted">{{ $voucher->beneficiary }} — ${{ number_format((float) $voucher->amount, 2) }}</small>
            </div>
            <div class="card-body">
                <form method="post" action="{{ backpack_url('internal-voucher/' . $voucher->id . '/anular') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="annulment_reason" class="form-label required">Motivo de la anulación</label>
                        <textarea name="annulment_reason" id="annulment_reason" class="form-control @error('annulment_reason') is-invalid @enderror" rows="4" minlength="10" maxlength="2000" required placeholder="Indique el motivo por el cual se anula este comprobante interno (mínimo 10 caracteres).">{{ old('annulment_reason') }}</textarea>
                        @error('annulment_reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="form-text text-muted">Mínimo 10 caracteres. Este motivo quedará registrado.</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-warning">
                            <i class="la la-ban"></i> Anular comprobante interno
                        </button>
                        <a href="{{ backpack_url('internal-voucher/' . $voucher->id . '/show') }}" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
