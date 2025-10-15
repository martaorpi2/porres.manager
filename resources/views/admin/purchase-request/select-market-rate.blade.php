@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>
            <span class="text-capitalize">Seleccionar Cotización Ganadora</span>
            <small id="datatable_info_stack">Solicitud: {{ $purchaseRequest->request_number }}</small>
        </h2>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4>Información de la Cotización</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Proveedor:</strong> {{ $marketRate->supplier->company_name }}<br>
                        <strong>Fecha de Cotización:</strong> {{ $marketRate->date->format('d/m/Y') }}<br>
                        <strong>Total:</strong> ${{ number_format($marketRate->total_amount, 2) }}
                    </div>
                    <div class="col-md-6">
                        <strong>Solicitud:</strong> {{ $purchaseRequest->request_number }}<br>
                        <strong>Área:</strong> {{ $purchaseRequest->responsibilityArea->name }}<br>
                        <strong>Prioridad:</strong> {{ $purchaseRequest->priority }}
                    </div>
                </div>
                
                <hr>
                
                <h5>Detalles de Productos:</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio Unitario</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($marketRate->quoteDetails as $detail)
                            <tr>
                                <td>{{ $detail->product->name ?? 'Producto no encontrado' }}</td>
                                <td>{{ $detail->quantity }}</td>
                                <td>${{ number_format($detail->unit_price, 2) }}</td>
                                <td>${{ number_format($detail->quantity * $detail->unit_price, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-info">
                                <th colspan="3" class="text-end">Total:</th>
                                <th>${{ number_format($marketRate->total_amount, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h4>Justificación de la Selección</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('purchase-request.store-market-rate-selection', [$purchaseRequest->id, $marketRate->id]) }}">
                    @csrf
                    
                    <div class="form-group">
                        <label for="justification">Justificación de la selección:</label>
                        <textarea 
                            name="justification" 
                            id="justification" 
                            class="form-control" 
                            rows="6" 
                            placeholder="Explique los criterios utilizados para seleccionar esta cotización (precio, calidad, plazo de entrega, confiabilidad del proveedor, etc.)"
                            required
                        ></textarea>
                        <small class="form-text text-muted">
                            Esta justificación quedará registrada para auditoría y seguimiento.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <div class="alert alert-info">
                            <i class="la la-info-circle"></i>
                            <strong>Importante:</strong> Al confirmar esta selección, la solicitud pasará a estado "Aprobada" y se podrá generar la orden de compra.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-success btn-block" onclick="return confirm('¿Está seguro de seleccionar esta cotización?')">
                            <i class="la la-check"></i> Confirmar Selección
                        </button>
                        <a href="{{ route('purchase-request.show', $purchaseRequest->id) }}" class="btn btn-secondary btn-block">
                            <i class="la la-arrow-left"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
