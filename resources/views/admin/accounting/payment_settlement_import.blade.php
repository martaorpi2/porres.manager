@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="mb-0 journal-title" bp-section="page-heading">Acreditaciones</h1>
        <a href="{{ backpack_url('accounting-journal') }}" class="btn journal-back ms-3 ml-3">Volver al diario</a>
    </section>
@endsection

@section('content')
@php
    $money = fn (int $cents) => number_format($cents / 100, 2, ',', '.');
    $showDate = function (?string $date): string {
        if ($date === null || $date === '') {
            return '—';
        }
        $parts = explode('-', $date);

        return count($parts) === 3 ? $parts[2].'/'.$parts[1].'/'.$parts[0] : $date;
    };
    $readyGross = 0;
    $readyCommission = 0;
    $readyInterest = 0;
    $readyNet = 0;
    foreach ($ready as $item) {
        $readyGross += $item['gross_cents'];
        $readyCommission += $item['commission_cents'];
        $readyInterest += $item['interest_cents'];
        $readyNet += $item['net_cents'];
    }
@endphp
<div class="row journal-page mp-import">
    <div class="col-12">
        <div class="card journal-card mb-3">
            <div class="card-body">
                @include('admin.accounting.inc.settlement_method')
                @if($definition)
                @foreach($definition['paragraphs'] as $paragraph)
                    <p class="mb-2">{{ $paragraph }}</p>
                @endforeach
                <div class="alert alert-info" role="alert">
                    <p class="mb-2">El asiento queda así:</p>
                    <ul class="mb-0">
                        @foreach($definition['entry_lines'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
                <form method="post" action="{{ backpack_url('accounting-settlement/'.$channel.'/preview') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-6">
                        <label for="archivo" class="form-label journal-label">{{ $definition['file_label'] }}</label>
                        <input type="file" name="archivo" id="archivo" class="form-control" accept="{{ $definition['accept'] }}" required>
                        @error('archivo')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn journal-btn-filter">Ver liquidación</button>
                    </div>
                </form>
                @else
                    <p class="mb-0">Seleccione la forma de pago para subir el archivo de liquidación.</p>
                @endif
            </div>
        </div>

        @if(is_array($rows))
            <div class="card journal-card journal-book mb-3">
                <div class="card-body">
                    <p class="mb-1">Liquidación <strong>{{ $document }}</strong>. <strong>{{ count($ready) }}</strong> {{ count($ready) === 1 ? 'asiento listo' : 'asientos listos' }} para registrar.</p>
                    @if($channel === 'qr')
                        <p class="mb-0">Bruto {{ $money($readyGross) }} · Comisión e IVA {{ $money($readyCommission) }} · Neto depositado {{ $money($readyNet) }}</p>
                    @else
                        <p class="mb-0">Bruto {{ $money($readyGross) }} · Comisión {{ $money($readyCommission) }} · Intereses {{ $money($readyInterest) }} · Neto {{ $money($readyNet) }}</p>
                    @endif
                </div>
            </div>

            <div class="card journal-card journal-book mb-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table journal-lines mb-0">
                            <thead>
                                @if($channel === 'naranja')
                                    <tr>
                                        <th>Fecha de compra</th>
                                        <th>Terminal-lote</th>
                                        <th>Presentación</th>
                                        <th>Plan</th>
                                        <th class="text-end">Importe</th>
                                        <th class="text-end">Arancel</th>
                                        <th class="text-end">Interés</th>
                                        <th>Operación</th>
                                        <th>Resultado</th>
                                    </tr>
                                @elseif($channel === 'sol')
                                    <tr>
                                        <th>Liquidación</th>
                                        <th>Fecha de pago</th>
                                        <th>Fecha de presentación</th>
                                        <th class="text-end">Presentado</th>
                                        <th class="text-end">Arancel e IVA</th>
                                        <th class="text-end">Costo financiero</th>
                                        <th class="text-end">Neto</th>
                                        <th>Resultado</th>
                                    </tr>
                                @else
                                    <tr>
                                        <th>Cupón</th>
                                        <th>Fecha</th>
                                        <th>Billetera</th>
                                        <th class="text-end">Bruto</th>
                                        <th class="text-end">Arancel</th>
                                        <th class="text-end">IVA</th>
                                        <th class="text-end">Neto</th>
                                        <th>Resultado</th>
                                    </tr>
                                @endif
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                    <tr>
                                        @if($channel === 'naranja')
                                            <td>{{ $showDate($row['purchase_date']) }}</td>
                                            <td>{{ $row['terminal'] }}</td>
                                            <td>{{ $row['coupon'] }} · {{ $row['coupons'] }} {{ $row['coupons'] === 1 ? 'cupón' : 'cupones' }}</td>
                                            <td>{{ $row['plan'] !== '' ? $row['plan'] : '—' }}</td>
                                            <td class="text-end text-nowrap">{{ $money($row['gross_cents']) }}</td>
                                            <td class="text-end text-nowrap">{{ $money($row['arancel_cents']) }}</td>
                                            <td class="text-end text-nowrap">{{ $money($row['interest_cents']) }}</td>
                                            <td>{{ $row['operation'] }}</td>
                                        @elseif($channel === 'sol')
                                            <td>{{ $row['liquidation'] }}</td>
                                            <td>{{ $showDate($row['date']) }}</td>
                                            <td>{{ $showDate($row['presented']) }}</td>
                                            <td class="text-end text-nowrap">{{ $money($row['gross_cents']) }}</td>
                                            <td class="text-end text-nowrap">{{ $money($row['commission_cents']) }}</td>
                                            <td class="text-end text-nowrap">{{ $money($row['interest_cents']) }}</td>
                                            <td class="text-end text-nowrap">{{ $money($row['net_cents']) }}</td>
                                        @else
                                            <td>{{ $row['coupon'] }}</td>
                                            <td>{{ $showDate($row['date']) }}</td>
                                            <td>{{ $row['wallet'] }}</td>
                                            <td class="text-end text-nowrap">{{ $money($row['gross_cents']) }}</td>
                                            <td class="text-end text-nowrap">{{ $money($row['arancel_cents']) }}</td>
                                            <td class="text-end text-nowrap">{{ $money($row['tax_cents']) }}</td>
                                            <td class="text-end text-nowrap">{{ $money($row['net_cents']) }}</td>
                                        @endif
                                        <td>
                                            <strong>{{ $row['outcome'] === 'ready' ? 'Se registra' : 'Ya registrada' }}</strong>
                                            <div class="text-muted small">{{ $row['detail'] }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if($ready !== [])
                <form method="post" action="{{ backpack_url('accounting-settlement/'.$channel) }}" id="settlement-register">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <button type="submit" class="btn journal-btn-filter" id="settlement-register-button">Registrar asientos</button>
                </form>
            @endif
        @endif
    </div>
</div>
@endsection

@section('after_styles')
<style>
    .journal-title { color: #1e2a4a; font-weight: 600; }
    .journal-back {
        background: #fff;
        border: 1px solid #d0d5dd;
        color: #344054 !important;
        font-weight: 600;
    }
    .journal-page .journal-card {
        background: #fff;
        border: 1px solid #e6e8ee;
        border-radius: 6px;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
    }
    .journal-label { font-weight: 700; color: #1e2a4a; font-size: 0.95rem; margin-bottom: 0.35rem; }
    .journal-btn-filter {
        background-color: #871f1f;
        border-color: #871f1f;
        color: #fff;
        font-weight: 600;
        padding: 0.45rem 1.1rem;
    }
    .journal-book { border-left: 4px solid #871f1f !important; }
    .journal-lines { width: 100%; border-collapse: collapse; margin-bottom: 0; }
    .journal-lines thead th {
        background: #871f1f !important;
        color: #fff !important;
        font-weight: 700;
        border: none;
        padding: 0.7rem 0.9rem;
    }
    .journal-lines tbody td {
        padding: 0.7rem 0.9rem;
        border-bottom: 1px solid #e6e8ee;
        background: #fff;
        vertical-align: middle;
        color: #1e2a4a;
    }
</style>
@endsection

@section('after_scripts')
<script>
    var form = document.getElementById('settlement-register');
    if (form) {
        form.addEventListener('submit', function () {
            var button = document.getElementById('settlement-register-button');
            button.disabled = true;
            button.textContent = 'Registrando…';
        });
    }
</script>
@endsection
