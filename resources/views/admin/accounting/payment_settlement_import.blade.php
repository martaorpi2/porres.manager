@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="mb-0 journal-title" bp-section="page-heading">Registración de Cobranzas</h1>
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
                @include('admin.accounting.inc.accreditation_format')
                @if($definition)
                @foreach($definition['paragraphs'] as $paragraph)
                    <p class="mb-2">{{ $paragraph }}</p>
                @endforeach
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
                        <button type="submit" class="btn journal-btn-filter">Ver acreditación</button>
                    </div>
                </form>
                @else
                    <p class="mb-0">Seleccione la forma de pago para subir el archivo de acreditación.</p>
                @endif
            </div>
        </div>

        @if(is_array($rows))
            <div class="card journal-card journal-book mb-3">
                <div class="card-body">
                    <p class="mb-1">Archivo <strong>{{ $document }}</strong>. <strong>{{ count($ready) }}</strong> {{ count($ready) === 1 ? 'asiento listo' : 'asientos listos' }} para registrar.</p>
                    <p class="mb-0">Bruto {{ $money($readyGross) }} · Comisión {{ $money($readyCommission) }} · Intereses {{ $money($readyInterest) }} · Neto {{ $money($readyNet) }}</p>
                </div>
            </div>

            @if($alerts !== [])
                <div id="settlement-mismatch-modal" class="settlement-modal" hidden>
                    <div class="settlement-modal-card" role="dialog" aria-modal="true" aria-labelledby="settlement-mismatch-title">
                        <h2 id="settlement-mismatch-title">Inconsistencias en la conciliación de cobranzas y acreditaciones</h2>
                        <p>Se detectaron diferencias entre los asientos de cobranza y las acreditaciones registradas por medio de pago. Revise los siguientes casos.</p>
                        <ul>
                            @foreach($alerts as $alert)
                                <li>{{ $alert }}</li>
                            @endforeach
                        </ul>
                        <p>Si aceptás, el asiento se guarda y el aviso queda en el inicio de Contabilidad hasta que los montos coincidan.</p>
                        <div class="settlement-modal-actions">
                            <button type="button" class="btn journal-back" id="settlement-mismatch-back">Volver</button>
                            <button type="button" class="btn journal-btn-filter" id="settlement-mismatch-accept">Aceptar</button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="card journal-card journal-book mb-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table journal-lines mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha de cobro</th>
                                    <th>Fecha de acreditación</th>
                                    <th class="text-end">Cobro</th>
                                    <th class="text-end">Cargos e impuestos</th>
                                    <th class="text-end">Intereses</th>
                                    <th class="text-end">Total a recibir</th>
                                    <th>Resultado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                    @php
                                        $outcomeLabel = [
                                            'ready' => 'Se registra',
                                            'update' => 'Se actualiza',
                                            'already_posted' => 'Ya registrada',
                                            'adjusted' => 'Modificado a mano',
                                            'unbalanced' => 'No cierra',
                                            'invalid' => 'Fecha inválida',
                                        ][$row['outcome']] ?? $row['outcome'];
                                    @endphp
                                    <tr>
                                        <td>{{ $showDate($row['collected_on'] ?? null) }}</td>
                                        <td>{{ $showDate($row['date']) }}</td>
                                        <td class="text-end text-nowrap">{{ $money($row['gross_cents']) }}</td>
                                        <td class="text-end text-nowrap">{{ $money($row['commission_cents']) }}</td>
                                        <td class="text-end text-nowrap">{{ $money($row['interest_cents']) }}</td>
                                        <td class="text-end text-nowrap">{{ $money($row['net_cents']) }}</td>
                                        <td>
                                            <strong>{{ $outcomeLabel }}</strong>
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
                    @if($alerts !== [])
                        <input type="hidden" name="confirm_mismatch" id="confirm-mismatch" value="0">
                    @endif
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
    .settlement-modal {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        background: rgba(16, 24, 40, 0.45);
    }
    .settlement-modal[hidden] {
        display: none;
    }
    .settlement-modal-card {
        width: min(36rem, 100%);
        background: #fff;
        border-radius: 8px;
        padding: 1.25rem 1.4rem;
        box-shadow: 0 12px 40px rgba(16, 24, 40, 0.2);
        color: #1e2a4a;
    }
    .settlement-modal-card h2 {
        font-size: 1.15rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
    }
    .settlement-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
    }
</style>
@endsection

@section('after_scripts')
<script>
    var form = document.getElementById('settlement-register');
    var modal = document.getElementById('settlement-mismatch-modal');
    var confirmInput = document.getElementById('confirm-mismatch');
    if (form) {
        form.addEventListener('submit', function (event) {
            if (modal && confirmInput && confirmInput.value !== '1') {
                event.preventDefault();
                modal.hidden = false;
                return;
            }
            var button = document.getElementById('settlement-register-button');
            if (button) {
                button.disabled = true;
                button.textContent = 'Registrando…';
            }
        });
    }
    if (modal && confirmInput && form) {
        document.getElementById('settlement-mismatch-accept').addEventListener('click', function () {
            confirmInput.value = '1';
            if (form.requestSubmit) {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
        document.getElementById('settlement-mismatch-back').addEventListener('click', function () {
            modal.hidden = true;
        });
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                modal.hidden = true;
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                modal.hidden = true;
            }
        });
    }
</script>
@endsection
