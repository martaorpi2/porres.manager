<script>
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-coupons-url]');
        if (!button) {
            return;
        }
        event.preventDefault();
        var panel = button.nextElementSibling;
        if (!panel) {
            return;
        }
        var open = panel.hidden;
        panel.hidden = !open;
        button.textContent = open
            ? (button.getAttribute('data-open-label') || 'Ocultar')
            : (button.getAttribute('data-closed-label') || 'Detalle');
        if (!open || panel.getAttribute('data-loaded') === '1') {
            return;
        }
        panel.innerHTML = '<p>Cargando…</p>';
        fetch(button.getAttribute('data-coupons-url'), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }, credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error();
                }
                return response.text();
            })
            .then(function (html) {
                panel.innerHTML = html;
                panel.setAttribute('data-loaded', '1');
            })
            .catch(function () {
                panel.innerHTML = '<p>No se pudo cargar el detalle.</p>';
            });
    });
</script>
