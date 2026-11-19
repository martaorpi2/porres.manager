@php
    $money = $money ?? fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp
@if($orders === [])
    <p class="mb-2">No hay cuotas.</p>
@else
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-2 bg-white">
            <thead>
                <tr>
                    <th>Orden</th>
                    <th>Cuota</th>
                    <th>Concepto</th>
                    <th>Vencimiento</th>
                    <th class="text-end">Importe</th>
                    <th class="text-end">Pagado</th>
                    <th>Estado</th>
                    <th>Fecha de pago</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orders as $order)
                    <tr>
                        <td>{{ $order['id'] }}</td>
                        <td>{{ $order['quota'] }}</td>
                        <td>{{ $order['concept'] }}</td>
                        <td>{{ $order['due_on'] }}</td>
                        <td class="text-end">${{ $money($order['amount']) }}</td>
                        <td class="text-end">${{ $money($order['paid_amount']) }}</td>
                        <td>{{ $order['state'] }}</td>
                        <td>{{ $order['paid_on'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
