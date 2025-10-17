@extends(backpack_view('blank'))

@section('header')
    <section class="content-header">
        <h1>
            <span class="text-capitalize">Asignar Productos</span>
            <small>{{ $generalRequest->number }}</small>
        </h1>
        <ol class="breadcrumb">
            <li><a href="{{ url(config('backpack.base.route_prefix', 'admin')) }}">{{ config('backpack.base.project_name') }}</a></li>
            <li><a href="{{ url(config('backpack.base.route_prefix', 'admin').'/product-assignment') }}">Asignación de Productos</a></li>
            <li class="active">{{ $generalRequest->number }}</li>
        </ol>
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <strong>{{ $generalRequest->title }}</strong>
                    </h3>
                    <div class="card-tools">
                        <span class="badge badge-info">{{ $generalRequest->area->name ?? 'Sin área' }}</span>
                        <span class="badge badge-secondary">{{ $generalRequest->createdBy->name ?? 'Usuario' }}</span>
                    </div>
                </div>
                
                <form method="POST" action="{{ route('product-assignment.assign', $generalRequest) }}">
                    @csrf
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad Solicitada</th>
                                        <th>Ubicación</th>
                                        <th>Stock Disponible</th>
                                        <th>Cantidad a Asignar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($generalRequest->details as $index => $detail)
                                        <tr>
                                            <td>
                                                <strong>{{ $detail->product->name }}</strong><br>
                                                <small class="text-muted">{{ $detail->product->description ?? 'Sin descripción' }}</small>
                                            </td>
                                            <td>
                                                <span class="badge badge-info">{{ $detail->requested_quantity }}</span>
                                            </td>
                                            <td>
                                                <select name="assignments[{{ $index }}][location_id]" class="form-control location-select" 
                                                        data-product-id="{{ $detail->product_id }}" required>
                                                    <option value="">Seleccionar ubicación</option>
                                                    @foreach($locations as $location)
                                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="hidden" name="assignments[{{ $index }}][product_id]" value="{{ $detail->product_id }}">
                                            </td>
                                            <td>
                                                <span class="stock-available" id="stock-{{ $detail->product_id }}">-</span>
                                                <div class="stock-info" id="stock-info-{{ $detail->product_id }}" style="display: none;">
                                                    <small class="text-muted">Stock total: <span class="total-stock">0</span></small><br>
                                                    <small class="text-info">Disponible en otras ubicaciones:</small>
                                                    <ul class="other-locations" style="margin: 0; padding-left: 15px; font-size: 11px;"></ul>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="number" name="assignments[{{ $index }}][assigned_quantity]" 
                                                       class="form-control assigned-quantity" min="1" max="0" required>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="la la-save"></i> Confirmar Asignación
                        </button>
                        <a href="{{ url(config('backpack.base.route_prefix', 'admin').'/product-assignment') }}" class="btn btn-secondary">
                            <i class="la la-arrow-left"></i> Volver
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('after_scripts')
    <script>
        $(document).ready(function() {
            // When location is selected, get available stock
            $('.location-select').on('change', function() {
                var productId = $(this).data('product-id');
                var locationId = $(this).val();
                var stockElement = $('#stock-' + productId);
                var quantityInput = $(this).closest('tr').find('.assigned-quantity');
                
                console.log('Location changed:', {productId, locationId, stockElement: stockElement.length, quantityInput: quantityInput.length});
                
                if (locationId) {
                    $.ajax({
                        url: '{{ route("product-assignment.get-stock") }}',
                        method: 'GET',
                        data: {
                            product_id: productId,
                            location_id: locationId
                        },
                        success: function(response) {
                            stockElement.text(response.available_quantity);
                            
                            // Mostrar información adicional de stock
                            var stockInfo = $('#stock-info-' + productId);
                            stockInfo.find('.total-stock').text(response.total_stock);
                            
                            // Limpiar lista de otras ubicaciones
                            var otherLocations = stockInfo.find('.other-locations');
                            otherLocations.empty();
                            
                            // Agregar ubicaciones con stock
                            if (response.stock_locations && response.stock_locations.length > 0) {
                                response.stock_locations.forEach(function(location) {
                                    if (location.location_id != locationId) {
                                        otherLocations.append('<li>' + location.location_name + ': ' + location.quantity + '</li>');
                                    }
                                });
                                stockInfo.show();
                            } else {
                                stockInfo.hide();
                            }
                            
                            // Permitir asignar hasta el stock total disponible
                            var maxQuantity = Math.max(response.available_quantity, response.total_stock);
                            quantityInput.attr('max', maxQuantity);
                            
                            // Validar cantidad actual si es mayor al nuevo máximo
                            var currentValue = parseInt(quantityInput.val()) || 0;
                            if (currentValue > maxQuantity) {
                                quantityInput.val(maxQuantity);
                                alert('La cantidad ingresada excede el stock disponible. Se ajustó a ' + maxQuantity);
                            }
                            
                            if (response.total_stock == 0) {
                                quantityInput.prop('disabled', true);
                                stockElement.addClass('text-danger');
                                stockElement.text('Sin stock');
                            } else {
                                quantityInput.prop('disabled', false);
                                stockElement.removeClass('text-danger');
                                
                                // Si no hay stock en esta ubicación pero hay en otras, mostrar mensaje
                                if (response.available_quantity == 0 && response.total_stock > 0) {
                                    stockElement.addClass('text-warning');
                                    stockElement.text('0 (disponible en otras ubicaciones)');
                                }
                            }
                        }
                    });
                } else {
                    stockElement.text('-');
                    quantityInput.attr('max', 0);
                    quantityInput.prop('disabled', true);
                }
            });
            
            // Validar cantidad ingresada manualmente
            $('.assigned-quantity').on('input', function() {
                var input = $(this);
                var maxValue = parseInt(input.attr('max'));
                var currentValue = parseInt(input.val());
                
                if (currentValue > maxValue) {
                    input.val(maxValue);
                    alert('No se puede asignar más cantidad que el stock disponible (' + maxValue + ')');
                }
            });
        });
    </script>
@endsection