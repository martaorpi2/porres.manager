@if($rows === [])
    <p class="mb-0">No hay planes de pago asociados a este asiento.</p>
@else
    <h6 class="text-primary border-bottom pb-2">{{ $heading }}</h6>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Comprobante</th>
                    <th>Estudiante</th>
                    <th>Plan</th>
                    <th class="text-center">Nº cuota</th>
                    <th class="text-end">Deudores</th>
                    <th class="text-end">Mora</th>
                    <th class="text-end">Financiación</th>
                    <th class="text-end">Cobrado</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $debtors = 0.0;
                    $interest = 0.0;
                    $financing = 0.0;
                    $bank = 0.0;
                @endphp
                @foreach($rows as $row)
                    @php
                        $debtors += $row['debtors'];
                        $interest += $row['interest'];
                        $financing += $row['financing'];
                        $bank += $row['bank'];
                    @endphp
                    <tr>
                        <td>
                            {{ $row['id'] }}
                            @if($row['note'] !== '')<span class="d-block" style="font-size:0.8rem;color:#6c757d;">{{ $row['note'] }}</span>@endif
                        </td>
                        <td>{{ $row['student'] }}</td>
                        <td>{{ $row['plan'] }}</td>
                        <td class="text-center">{{ $row['quota'] }}</td>
                        <td class="text-end">${{ number_format($row['debtors'], 2, ',', '.') }}</td>
                        <td class="text-end">${{ number_format($row['interest'], 2, ',', '.') }}</td>
                        <td class="text-end">${{ number_format($row['financing'], 2, ',', '.') }}</td>
                        <td class="text-end">${{ number_format($row['bank'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="4">Totales</td>
                    <td class="text-end">${{ number_format($debtors, 2, ',', '.') }}</td>
                    <td class="text-end">${{ number_format($interest, 2, ',', '.') }}</td>
                    <td class="text-end">${{ number_format($financing, 2, ',', '.') }}</td>
                    <td class="text-end">${{ number_format($bank, 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
