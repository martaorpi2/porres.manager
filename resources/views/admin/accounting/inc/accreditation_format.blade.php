<p class="mb-2">Cada medio de pago se sube en su propio Excel. Elegí la forma de pago y cargá el archivo de ese medio.</p>
<div class="alert alert-info" role="alert">
    <p class="mb-2">Los archivos tienen que usar estos nombres de columna. El nombre es estricto: no se aceptan otros, como Fecha, Importe o Comisión.</p>
    <ul class="mb-2">
        <li><strong>Fecha de cobro.</strong> Es el día en que la tesorera registró el pago.</li>
        <li><strong>Nro de cupón POSNET.</strong> Obligatoria en QR, Naranja X y Sol Pago. Es el número de cupón del POS. Se compara con el que quedó guardado al cobrar, en QR, débito, crédito, Naranja y Sol, para relacionar ese cupón con el asiento de cobranza. En Naranja X también se buscan débito, crédito y Naranja débito. Mercado Pago no lleva esta columna.</li>
        <li><strong>Fecha de acreditación</strong></li>
        <li><strong>Cobro</strong></li>
        <li><strong>Cargos e impuestos</strong></li>
        <li><strong>Total a recibir</strong></li>
        <li><strong>Intereses.</strong> Esta columna no es obligatoria. Si no está, se toma como 0.</li>
    </ul>
    <p class="mb-2">No importan mayúsculas, acentos ni espacios de más.</p>
    <p class="mb-0">El cobro es lo que pagó el alumno. De ese importe se restan la comisión y los intereses, y lo que queda es el total que se acredita. Las filas de la misma fecha de acreditación se suman en un asiento. En QR, Naranja X y Sol Pago el cupón indica qué asiento de cobranza cierra. En Mercado Pago se sigue usando la fecha de cobro.</p>
</div>
