@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="mb-0" bp-section="page-heading">Liberación Mercado Pago</h1>
    </section>
@endsection

@section('content')
<style>
    .mp-release-scroll {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        font-size: 0.9rem;
    }
    .mp-release-table { margin-bottom: 0; white-space: nowrap; }
    .mp-release-table th,
    .mp-release-table td { vertical-align: middle; }
</style>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Resumen</h5></div>
    <div class="card-body">
        <span class="badge bg-warning text-dark">Pendientes: {{ $pendingCount }} · ${{ number_format($pendingAmount, 2, ',', '.') }}</span>
        <span class="badge bg-success">Liberados: {{ $releasedCount }} · ${{ number_format($releasedAmount, 2, ',', '.') }}</span>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Filtros</h5></div>
    <div class="card-body">
        <form method="GET" action="{{ backpack_url('accounting-mercadopago-releases') }}" class="row g-3">
            <div class="col-md-2">
                <label for="fecha_desde" class="form-label">Liberación desde</label>
                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" value="{{ $fechaDesde }}">
            </div>
            <div class="col-md-2">
                <label for="fecha_hasta" class="form-label">Liberación hasta</label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" value="{{ $fechaHasta }}">
            </div>
            <div class="col-md-2">
                <label for="release_status" class="form-label">Liberación</label>
                <select name="release_status" id="release_status" class="form-control">
                    <option value="" {{ $releaseStatus === '' ? 'selected' : '' }}>Todas</option>
                    <option value="pending" {{ $releaseStatus === 'pending' ? 'selected' : '' }}>Pendiente</option>
                    <option value="released" {{ $releaseStatus === 'released' ? 'selected' : '' }}>Liberado</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Estado MP</label>
                <select name="status" id="status" class="form-control">
                    <option value="" {{ $mpStatus === '' ? 'selected' : '' }}>Todos</option>
                    @foreach($statusOptions as $opt)
                        <option value="{{ $opt }}" {{ $mpStatus === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="order_id" class="form-label">Orden</label>
                <input type="number" min="1" name="order_id" id="order_id" class="form-control" value="{{ $orderId }}">
            </div>
            <div class="col-md-2">
                <label for="payment_id" class="form-label">Nro de transacción</label>
                <input type="text" name="payment_id" id="payment_id" class="form-control" value="{{ $paymentId }}">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="{{ backpack_url('accounting-mercadopago-releases') }}" class="btn btn-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Pagos</h5></div>
    <div class="card-body">
        <div class="mp-release-scroll">
            <table class="table table-sm table-hover mp-release-table">
                <thead class="table-light">
                    <tr>
                        <th>Fecha de liberación</th>
                        <th>Liberación</th>
                        <th>Aprobado</th>
                        <th>Orden</th>
                        <th>Estudiante</th>
                        <th>Nro de transacción</th>
                        <th>Estado MP</th>
                        <th class="text-end">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $row)
                        @php
                            $event = $row->event;
                            $order = $event->order;
                            $student = $order ? $order->student : null;
                        @endphp
                        <tr>
                            <td>{{ $row->release_at ? date('d/m/Y H:i', strtotime($row->release_at)) : ($row->release_raw ?: '-') }}</td>
                            <td>
                                @if($row->release_status === 'released')
                                    <span class="badge bg-success">Liberado</span>
                                @elseif($row->release_status === 'pending')
                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                @else
                                    <span class="badge bg-secondary">{{ $row->release_status !== '' ? $row->release_status : 'Sin dato' }}</span>
                                @endif
                            </td>
                            <td>{{ optional($event->date_approved)->format('d/m/Y H:i') ?: '-' }}</td>
                            <td>{{ $order ? $order->id : ($event->order_id ?: '-') }}</td>
                            <td>{{ $student ? $student->last_name.', '.$student->first_name.' - '.($student->dni ?: 's/d') : '-' }}</td>
                            <td>{{ $event->payment_id }}</td>
                            <td>
                                <div><strong>{{ $row->mp_status !== '' ? $row->mp_status : '-' }}</strong></div>
                                <small class="text-muted">{{ $event->status_detail ?: '' }}</small>
                            </td>
                            <td class="text-end">${{ number_format($row->amount, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">No hay pagos para los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-end mt-3">
            {{ $payments->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
