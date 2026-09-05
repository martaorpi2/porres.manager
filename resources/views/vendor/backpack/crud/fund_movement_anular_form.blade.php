@extends(backpack_view('blank'))

@php
    $defaultBreadcrumbs = [
        trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
        'Egresos' => backpack_url('fund-movement'),
        $movement->number => backpack_url('fund-movement/' . $movement->id . '/show'),
        'Anular' => false,
    ];
    $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
@endphp

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="text-capitalize mb-0" bp-section="page-heading">Anular egreso</h1>
        <p class="ms-2 ml-2 mb-0" bp-section="page-subheading">{{ $movement->number }}</p>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body">
                <form method="post" action="{{ backpack_url('fund-movement/' . $movement->id . '/anular') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="annulment_reason" class="form-label required">Motivo de la anulación</label>
                        <textarea name="annulment_reason" id="annulment_reason" class="form-control @error('annulment_reason') is-invalid @enderror" rows="4" minlength="10" maxlength="2000" required>{{ old('annulment_reason') }}</textarea>
                        @error('annulment_reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-warning"><i class="la la-ban"></i> Anular egreso</button>
                    <a href="{{ backpack_url('fund-movement/' . $movement->id . '/show') }}" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
