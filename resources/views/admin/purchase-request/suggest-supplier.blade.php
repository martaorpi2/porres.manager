@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>
            <span class="text-capitalize">Sugerir Proveedor</span>
            <small id="datatable_info_stack">Solicitud: {{ $purchaseRequest->request_number }}</small>
        </h2>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4>Información de la Solicitud de Compra</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Número de Solicitud:</strong> {{ $purchaseRequest->request_number }}<br>
                        <strong>Fecha:</strong> {{ $purchaseRequest->request_date->format('d/m/Y') }}<br>
                        <strong>Estado:</strong> <span class="badge bg-{{ $purchaseRequest->status == 'Pendiente' ? 'warning' : ($purchaseRequest->status == 'Aprobada' ? 'success' : 'secondary') }}">{{ $purchaseRequest->status }}</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Área:</strong> {{ $purchaseRequest->responsibilityArea->name ?? 'N/A' }}<br>
                        <strong>Prioridad:</strong> {{ $purchaseRequest->priority }}<br>
                        <strong>Total:</strong> ${{ number_format($purchaseRequest->total_amount, 2) }}
                    </div>
                </div>
                
                <hr>
                
                <h5>Productos Solicitados:</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Unidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchaseRequest->details as $detail)
                            <tr>
                                <td>{{ $detail->product->name ?? 'Producto no encontrado' }}</td>
                                <td>{{ $detail->requested_quantity }}</td>
                                <td>{{ $detail->product->unit_measurement ?? 'unidad' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h4>Sugerir Proveedor</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('purchase-request.store-supplier-suggestion', $purchaseRequest->id) }}">
                    @csrf
                    
                    <div class="form-group mb-3">
                        <label for="supplier_id">Proveedor:</label>
                        <select 
                            name="supplier_id" 
                            id="supplier_id" 
                            class="form-control" 
                            required
                        >
                            <option value="">Seleccione un proveedor...</option>
                            @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->company_name ?? 'Proveedor #' . $supplier->id }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">
                            Seleccione el proveedor que desea sugerir para esta solicitud.
                        </small>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="justification">Justificación de la sugerencia:</label>
                        <textarea 
                            name="justification" 
                            id="justification" 
                            class="form-control" 
                            rows="6" 
                            placeholder="Explique por qué sugiere este proveedor (experiencia previa, calidad, precio, servicio, etc.)"
                            required
                        ></textarea>
                        <small class="form-text text-muted">
                            Esta justificación ayudará al responsable de compras a tomar una decisión informada.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <div class="alert alert-info">
                            <i class="la la-info-circle"></i>
                            <strong>Nota:</strong> Esta es solo una sugerencia. El responsable de compras será quien finalmente seleccione la cotización ganadora.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-info btn-block">
                            <i class="la la-lightbulb"></i> Enviar Sugerencia
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

