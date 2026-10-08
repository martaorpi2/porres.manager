<p class="mb-2">Cada medio de pago se sube en su propio Excel. Los cuatro archivos tienen las mismas columnas. Elegí la forma de pago y cargá el archivo de ese medio.</p>
<div class="alert alert-info" role="alert">
    <p class="mb-2">Los archivos tienen que usar estos nombres de columna. El nombre es estricto: no se aceptan otros, como Fecha, Importe o Comisión.</p>
    <ul class="mb-2">
        <li><strong>Fecha de cobro.</strong> Es el día en que la tesorera registró el pago. Con esa fecha se cruza el archivo con el asiento de cobranza.</li>
        <li><strong>Fecha de acreditación</strong></li>
        <li><strong>Cobro</strong></li>
        <li><strong>Cargos e impuestos</strong></li>
        <li><strong>Total a recibir</strong></li>
        <li><strong>Intereses.</strong> Esta columna no es obligatoria. Si no está, se toma como 0.</li>
    </ul>
    <p class="mb-2">No importan mayúsculas, acentos ni espacios de más.</p>
    <p class="mb-0">El cobro es lo que pagó el alumno. De ese importe se restan la comisión y los intereses, y lo que queda es el total que se acredita. Las filas de la misma fecha de acreditación se suman en un asiento. La fecha de cobro indica qué asiento de la tesorera cierra esa liquidación.</p>
</div>
