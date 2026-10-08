@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="mb-0 journal-title" bp-section="page-heading">Liquidación Mercado Pago</h1>
        <a href="{{ backpack_url('accounting-journal') }}" class="btn journal-back ms-3 ml-3">Volver al diario</a>
    </section>
@endsection

@section('content')
@php
    $money = fn (int $cents) => number_format($cents / 100, 2, ',', '.');
    $outcomes = [
        'ready' => 'Se registra',
        'already_posted' => 'Ya registrada',
        'unmatched' => 'Sin orden en ePorres',
        'not_approved' => 'No aprobada',
        'unbalanced' => 'No cierra',
        'amount_mismatch' => 'Importe distinto',
        'not_collected' => 'Sin cuota paga',
        'invalid' => 'Fecha inválida',
    ];
    $readyGross = 0;
    $readyCommission = 0;
    $readyNet = 0;
    $byDate = [];
    foreach ($ready as $item) {
        $readyGross += $item['gross_cents'];
        $readyCommission += $item['commission_cents'];
        $readyNet += $item['net_cents'];
        $byDate[$item['date']] = ($byDate[$item['date']] ?? 0) + 1;
    }
    ksort($byDate);
@endphp
<div class="row journal-page mp-import">
    <div class="col-12">
        <div class="card journal-card mb-3">
            <div class="card-body">
                <p class="mb-2">Seleccione el archivo de Mercado Pago correspondiente a las operaciones a importar. Para otros medios usá <a href="{{ backpack_url('accounting-settlement/naranja') }}">Naranja X</a>, <a href="{{ backpack_url('accounting-settlement/sol') }}">Sol Pago</a> o <a href="{{ backpack_url('accounting-settlement/qr') }}">QR</a>.</p>
                <p class="mb-2">El archivo debe contener los siguientes campos:</p>
                <ul class="mb-3">
                    <li>Número de operación</li>
                    <li>Fecha de la compra</li>
                    <li>Estado</li>
                    <li>Cobro</li>
                    <li>Cargos e impuestos</li>
                    <li>Total a recibir</li>
                </ul>
                <div class="alert alert-info" role="alert">
                    <p class="mb-2">Cada operación aprobada que coincide con un <strong>cupón pagado en ePorres</strong> genera la correspondiente liquidación:</p>
                    <ul class="mb-2">
                        <li><strong>Mercado Pago:</strong> por el importe neto recibido.</li>
                        <li><strong>Comisión:</strong> por los cargos y comisiones correspondientes.</li>
                        <li><strong>Mercado Pago a cobrar:</strong> por el importe bruto de la operación.</li>
                    </ul>
                    <p class="mb-0">Las operaciones que no se encuentren aprobadas o que no coincidan con un cupón pagado en ePorres <strong>no se procesan</strong>.</p>
                </div>
                <form method="post" action="{{ backpack_url('accounting-mercadopago/preview') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-6">
                        <label for="archivo" class="form-label journal-label">Archivo de ventas de Mercado Pago</label>
                        <input type="file" name="archivo" id="archivo" class="form-control" accept=".xlsx,.xls" required>
                        @error('archivo')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn journal-btn-filter">Ver operaciones</button>
                    </div>
                </form>
            </div>
        </div>

        @if(is_array($rows))
            <div class="card journal-card journal-book mb-3">
                <div class="card-body">
                    <p class="mb-1"><strong>{{ count($ready) }}</strong> {{ count($ready) === 1 ? 'orden lista para asentar' : 'órdenes listas para asentar' }}, en {{ count($byDate) }} {{ count($byDate) === 1 ? 'asiento' : 'asientos' }}.</p>
                    <p class="mb-0">Cobro {{ $money($readyGross) }} · Comisión {{ $money($readyCommission) }} · A recibir {{ $money($readyNet) }}</p>
                </div>
            </div>

            <div class="card journal-card journal-book mb-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table journal-lines mb-0">
                            <thead>
                                <tr>
                                    <th>Número de operación</th>
                                    <th>Fecha de la compra</th>
                                    <th>Estado</th>
                                    <th class="text-end">Cobro</th>
                                    <th class="text-end">Comisión</th>
                                    <th class="text-end">Total a recibir</th>
                                    <th>Orden ePorres</th>
                                    <th>Resultado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                    @php
                                        $dateLabel = $row['date'] ? \Carbon\Carbon::parse($row['date'])->format('d/m/Y') : '—';
                                    @endphp
                                    <tr>
                                        <td>{{ $row['operation'] }}</td>
                                        <td>{{ $dateLabel }}</td>
                                        <td>{{ $row['status'] !== '' ? $row['status'] : '—' }}</td>
                                        <td class="text-end text-nowrap">{{ $money($row['gross_cents']) }}</td>
                                        <td class="text-end text-nowrap">{{ $money($row['commission_cents']) }}</td>
                                        <td class="text-end text-nowrap">{{ $money($row['net_cents']) }}</td>
                                        <td>{{ $row['order_label'] ?? '—' }}</td>
                                        <td>
                                            <strong>{{ $outcomes[$row['outcome']] ?? $row['outcome'] }}</strong>
                                            @if($row['entry_number'])
                                                <span class="journal-asiento-meta">{{ $row['entry_number'] }}</span>
                                            @endif
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
                <form method="post" action="{{ backpack_url('accounting-mercadopago') }}" id="mp-register">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <button type="submit" class="btn journal-btn-filter" id="mp-register-button">Registrar asientos</button>
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
    .journal-asiento-meta { margin-left: 0.75rem; font-weight: 400; }
</style>
@endsection

@section('after_scripts')
<script>
    var form = document.getElementById('mp-register');
    if (form) {
        form.addEventListener('submit', function () {
            var button = document.getElementById('mp-register-button');
            button.disabled = true;
            button.textContent = 'Registrando…';
        });
    }
</script>
@endsection
