<p class="mb-2">Cada medio de pago se sube en su propio Excel. Los cuatro archivos tienen las mismas columnas. Elegí la forma de pago y cargá el archivo de ese medio.</p>
<p class="mb-2">Una fila por cupón u operación. En Naranja X, si el archivo viene por día, una fila por día con los totales de ese día: no hace falta desglosar cupones.</p>
<p class="mb-2">Columnas, iguales en todos los archivos:</p>
<ul class="mb-3">
    <li>Número de operación</li>
    <li>Fecha de acreditación</li>
    <li>Estado</li>
    <li>Cobro</li>
    <li>Cargos e impuestos</li>
    <li>Intereses (0 en Mercado Pago y QR)</li>
    <li>Total a recibir</li>
</ul>
<p class="mb-3">Solo se registran las filas con estado <strong>Aprobado</strong>. El cobro es lo que pagó el alumno. De ese importe se restan la comisión (cargos e impuestos) y los intereses, y lo que queda es el total que se acredita. Las filas de la misma fecha se suman en un asiento.</p>
