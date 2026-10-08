@php
    $settlementMethods = [
        'mercadopago' => ['label' => 'Mercado Pago', 'url' => backpack_url('accounting-settlement/mercadopago')],
        'naranja' => ['label' => 'Naranja X', 'url' => backpack_url('accounting-settlement/naranja')],
        'sol' => ['label' => 'Sol Pago', 'url' => backpack_url('accounting-settlement/sol')],
        'qr' => ['label' => 'QR', 'url' => backpack_url('accounting-settlement/qr')],
    ];
    $selectedMethod = $channel ?? '';
@endphp
<div class="mb-3" style="max-width: 28rem;">
    <label for="forma-pago" class="form-label journal-label">Forma de pago</label>
    <select id="forma-pago" class="form-control">
        <option value="{{ backpack_url('accounting-settlement') }}" @selected($selectedMethod === '')>Seleccione</option>
        @foreach ($settlementMethods as $key => $method)
            <option value="{{ $method['url'] }}" @selected($selectedMethod === $key)>{{ $method['label'] }}</option>
        @endforeach
    </select>
</div>
<script>
    document.getElementById('forma-pago').addEventListener('change', function () {
        if (this.value) {
            window.location = this.value;
        }
    });
</script>
