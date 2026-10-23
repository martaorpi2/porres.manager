@if($plans === [] && $missing === [])
    <p class="mb-0">No hay planes de pago asociados a este asiento.</p>
@endif
@foreach($plans as $plan)
    <div class="mb-4">
        <h6 class="text-primary border-bottom pb-2">
            Plan #{{ $plan['id'] }}
            <span class="fw-normal">· {{ $plan['student'] }}</span>
        </h6>
        <p class="mb-2">
            Alta {{ $plan['opened_on'] }}
            @if($plan['dues'] > 0)
                · {{ $plan['dues'] }} {{ $plan['dues'] === 1 ? 'cuota' : 'cuotas' }}
            @endif
            · Total del plan ${{ number_format($plan['total'], 2, ',', '.') }}
        </p>

        <p class="mb-1"><strong>Cuotas que debía</strong></p>
        @if($plan['debts'] === [])
            <p class="mb-3">Este plan no tiene cuotas originales asociadas.</p>
        @else
            <div class="table-responsive mb-3">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Comprobante</th>
                            <th>Concepto</th>
                            <th class="text-center">Nº</th>
                            <th class="text-end">Deuda</th>
                            <th class="text-end">Mora</th>
                            <th class="text-end">Financiación</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($plan['debts'] as $debt)
                            <tr>
                                <td>{{ $debt['id'] }}</td>
                                <td>{{ $debt['concept'] }}</td>
                                <td class="text-center">{{ $debt['quota'] }}</td>
                                <td class="text-end">${{ number_format($debt['debt'], 2, ',', '.') }}</td>
                                <td class="text-end">${{ number_format($debt['late'], 2, ',', '.') }}</td>
                                <td class="text-end">${{ number_format($debt['financing'], 2, ',', '.') }}</td>
                                <td class="text-end">${{ number_format($debt['total'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3">Total que debía</td>
                            <td class="text-end">${{ number_format($plan['debt'], 2, ',', '.') }}</td>
                            <td class="text-end">${{ number_format($plan['late'], 2, ',', '.') }}</td>
                            <td class="text-end">${{ number_format($plan['financing'], 2, ',', '.') }}</td>
                            <td class="text-end">${{ number_format($plan['total'], 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        <p class="mb-1"><strong>Cuotas del plan</strong></p>
        @if($plan['installments'] === [])
            <p class="mb-0">Este plan no tiene cuotas generadas.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center">Cuota</th>
                            <th>Vencimiento</th>
                            <th class="text-end">Importe</th>
                            <th class="text-end">Mora</th>
                            <th class="text-end">Cobrado</th>
                            <th>Estado</th>
                            <th>Fecha de pago</th>
                            <th>Medio</th>
                            <th>Cupón POS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($plan['installments'] as $installment)
                            <tr>
                                <td class="text-center">{{ $installment['number'] }}</td>
                                <td>{{ $installment['due_on'] }}</td>
                                <td class="text-end">${{ number_format($installment['amount'], 2, ',', '.') }}</td>
                                <td class="text-end">${{ number_format($installment['late'], 2, ',', '.') }}</td>
                                <td class="text-end">${{ number_format($installment['paid'], 2, ',', '.') }}</td>
                                <td>{{ $installment['state'] }}</td>
                                <td>{{ $installment['paid_on'] }}</td>
                                <td>{{ $installment['payment_type'] }}</td>
                                <td>{{ $installment['coupon'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="2">Totales del plan</td>
                            <td class="text-end">${{ number_format($plan['installment_total'], 2, ',', '.') }}</td>
                            <td></td>
                            <td class="text-end">${{ number_format($plan['paid_total'], 2, ',', '.') }}</td>
                            <td colspan="4">Pendiente ${{ number_format($plan['pending_total'], 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
@endforeach
@if($missing !== [])
    <p class="mb-2">No se encontró el plan de {{ count($missing) === 1 ? 'este comprobante' : 'estos comprobantes' }}.</p>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Comprobante</th>
                    <th class="text-end">Deuda</th>
                    <th class="text-end">Mora</th>
                    <th class="text-end">Financiación</th>
                </tr>
            </thead>
            <tbody>
                @foreach($missing as $row)
                    <tr>
                        <td>{{ $row['id'] }}</td>
                        <td class="text-end">${{ number_format($row['debtors'], 2, ',', '.') }}</td>
                        <td class="text-end">${{ number_format($row['interest'], 2, ',', '.') }}</td>
                        <td class="text-end">${{ number_format($row['financing'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
