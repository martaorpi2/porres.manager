{{-- Modal de edición de cotización sobre la ficha de solicitud. Se mueve a document.body para el z-index. --}}
<style>
    #prEditQuotationModal {
        z-index: 10850 !important;
    }
    #prEditQuotationModal .modal-dialog {
        z-index: 10851;
        pointer-events: auto;
        max-width: 960px;
    }
    #prEditQuotationModal .modal-content {
        pointer-events: auto;
    }
    .modal-backdrop.pr-edit-quotation-backdrop {
        z-index: 10840 !important;
    }
    #prEditQuotationModal .pr-quote-item-row .form-control {
        min-width: 0;
    }
</style>

<div class="modal fade" id="prEditQuotationModal" tabindex="-1" aria-labelledby="prEditQuotationModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="prEditQuotationForm" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="purchase_request_id" id="prEqPurchaseRequestId" value="">
                <input type="hidden" name="selected_quote_items" id="prEqSelectedItems" value="[]">
                <div class="modal-header">
                    <h5 class="modal-title" id="prEditQuotationModalLabel">Editar cotización</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="prEqAlert" class="alert alert-danger d-none"></div>
                    <div id="prEqLoading" class="text-muted mb-2">Cargando cotización…</div>
                    <div id="prEqFields" class="d-none">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label" for="prEqSupplier">Proveedor</label>
                                <select class="form-select" name="supplier_id" id="prEqSupplier" required></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="prEqDate">Fecha</label>
                                <input type="date" class="form-control" name="date" id="prEqDate" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="prEqTotal">Monto total</label>
                                <input type="text" class="form-control" name="total_amount" id="prEqTotal" inputmode="decimal" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="prEqDeliveryDate">Entrega estimada</label>
                                <input type="date" class="form-control" name="delivery_date" id="prEqDeliveryDate">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="prEqDeliveryTerm">Plazo</label>
                                <input type="text" class="form-control" name="delivery_term" id="prEqDeliveryTerm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="prEqPayment">Forma de pago</label>
                                <input type="text" class="form-control" name="payment_method" id="prEqPayment">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="prEqValidity">Validez</label>
                                <input type="text" class="form-control" name="validity_term" id="prEqValidity">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="prEqFiles">Archivos (se agregan a los existentes)</label>
                                <input type="file" class="form-control" name="document_files[]" id="prEqFiles" multiple>
                                <div id="prEqExistingFiles" class="small mt-1"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="prEqLinks">Enlaces (uno por línea)</label>
                                <textarea class="form-control" name="reference_links" id="prEqLinks" rows="3"></textarea>
                            </div>
                        </div>
                        <hr>
                        <label class="form-label">Ítems de la cotización</label>
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-5">
                                <select class="form-select" id="prEqProductSelect">
                                    <option value="">Agregar producto de la solicitud…</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-primary w-100" id="prEqAddItem">Agregar</button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th style="width:110px;">Cantidad</th>
                                        <th style="width:130px;">Precio u.</th>
                                        <th>Descripción</th>
                                        <th style="width:50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="prEqItemsBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning" id="prEqSubmit">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const modalEl = document.getElementById('prEditQuotationModal');
    if (!modalEl || typeof bootstrap === 'undefined') {
        return;
    }

    document.body.appendChild(modalEl);

    const form = document.getElementById('prEditQuotationForm');
    const alertBox = document.getElementById('prEqAlert');
    const loading = document.getElementById('prEqLoading');
    const fields = document.getElementById('prEqFields');
    const itemsBody = document.getElementById('prEqItemsBody');
    const selectedItemsInput = document.getElementById('prEqSelectedItems');
    const productSelect = document.getElementById('prEqProductSelect');
    const totalInput = document.getElementById('prEqTotal');
    const submitBtn = document.getElementById('prEqSubmit');
    let products = [];
    let modalInstance = null;

    function showAlert(message) {
        alertBox.textContent = message;
        alertBox.classList.toggle('d-none', !message);
    }

    function syncHiddenItems() {
        const items = [];
        itemsBody.querySelectorAll('tr').forEach(function (row) {
            items.push({
                product_id: row.getAttribute('data-product-id'),
                product_name: row.querySelector('.pr-eq-name').textContent,
                quantity: row.querySelector('.pr-eq-qty').value,
                unit_price: row.querySelector('.pr-eq-price').value,
                product_description: row.querySelector('.pr-eq-desc').value
            });
        });
        selectedItemsInput.value = JSON.stringify(items);
        let sum = 0;
        items.forEach(function (item) {
            sum += (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
        });
        if (sum > 0 && (!totalInput.value || parseFloat(String(totalInput.value).replace(',', '.')) <= 0)) {
            totalInput.value = sum.toFixed(2);
        }
    }

    function addItemRow(item) {
        const tr = document.createElement('tr');
        tr.className = 'pr-quote-item-row';
        tr.setAttribute('data-product-id', item.product_id);
        tr.innerHTML =
            '<td class="pr-eq-name"></td>' +
            '<td><input type="number" min="1" step="any" class="form-control form-control-sm pr-eq-qty"></td>' +
            '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm pr-eq-price"></td>' +
            '<td><input type="text" class="form-control form-control-sm pr-eq-desc"></td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger pr-eq-remove">&times;</button></td>';
        tr.querySelector('.pr-eq-name').textContent = item.product_name || ('Producto #' + item.product_id);
        tr.querySelector('.pr-eq-qty').value = item.quantity || 1;
        tr.querySelector('.pr-eq-price').value = item.unit_price || 0;
        tr.querySelector('.pr-eq-desc').value = item.product_description || '';
        tr.querySelector('.pr-eq-remove').addEventListener('click', function () {
            tr.remove();
            syncHiddenItems();
        });
        tr.querySelector('.pr-eq-qty').addEventListener('input', syncHiddenItems);
        tr.querySelector('.pr-eq-price').addEventListener('input', syncHiddenItems);
        tr.querySelector('.pr-eq-desc').addEventListener('input', syncHiddenItems);
        itemsBody.appendChild(tr);
        syncHiddenItems();
    }

    function fillForm(data) {
        document.getElementById('prEqPurchaseRequestId').value = {{ (int) ($entry->id ?? 0) }};
        const supplierSelect = document.getElementById('prEqSupplier');
        supplierSelect.innerHTML = '';
        (data.suppliers || []).forEach(function (supplier) {
            const opt = document.createElement('option');
            opt.value = supplier.id;
            opt.textContent = supplier.name;
            if (Number(supplier.id) === Number(data.supplier_id)) {
                opt.selected = true;
            }
            supplierSelect.appendChild(opt);
        });
        document.getElementById('prEqDate').value = data.date || '';
        document.getElementById('prEqDeliveryDate').value = data.delivery_date || '';
        document.getElementById('prEqDeliveryTerm').value = data.delivery_term || '';
        document.getElementById('prEqPayment').value = data.payment_method || '';
        document.getElementById('prEqValidity').value = data.validity_term || '';
        totalInput.value = data.total_amount || '';
        document.getElementById('prEqLinks').value = data.reference_links || '';
        document.getElementById('prEqFiles').value = '';

        const filesWrap = document.getElementById('prEqExistingFiles');
        filesWrap.innerHTML = '';
        (data.files || []).forEach(function (file) {
            const label = document.createElement('label');
            label.className = 'd-block';
            label.innerHTML = '<input type="checkbox" name="clear_document_files[]" value=""> Quitar ' +
                '<a href="" target="_blank" rel="noopener"></a>';
            label.querySelector('input').value = file.path;
            const link = label.querySelector('a');
            link.href = file.url;
            link.textContent = file.label;
            filesWrap.appendChild(label);
        });

        products = data.products || [];
        productSelect.innerHTML = '<option value="">Agregar producto de la solicitud…</option>';
        products.forEach(function (product) {
            const opt = document.createElement('option');
            opt.value = product.id;
            opt.textContent = product.name;
            opt.setAttribute('data-qty', product.quantity || 1);
            opt.setAttribute('data-description', product.description || '');
            productSelect.appendChild(opt);
        });

        itemsBody.innerHTML = '';
        (data.items || []).forEach(addItemRow);
        form.setAttribute('action', data.update_url);
        syncHiddenItems();
    }

    function getModal() {
        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true, focus: true });
        }
        return modalInstance;
    }

    modalEl.addEventListener('shown.bs.modal', function () {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        const backdrop = backdrops[backdrops.length - 1];
        if (backdrop) {
            backdrop.classList.add('pr-edit-quotation-backdrop');
            document.body.appendChild(backdrop);
        }
        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
        document.body.style.paddingRight = '0px';
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        document.querySelectorAll('.modal-backdrop.pr-edit-quotation-backdrop').forEach(function (el) {
            el.remove();
        });
        if (!document.querySelector('.modal.show')) {
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }
        showAlert('');
    });

    document.addEventListener('click', function (event) {
        const btn = event.target.closest('.js-pr-edit-quotation');
        if (!btn) {
            return;
        }
        event.preventDefault();
        const url = btn.getAttribute('data-edit-url');
        if (!url) {
            return;
        }
        showAlert('');
        loading.classList.remove('d-none');
        fields.classList.add('d-none');
        submitBtn.disabled = true;
        getModal().show();
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('No se pudo cargar la cotización.');
                }
                return response.json();
            })
            .then(function (data) {
                fillForm(data);
                loading.classList.add('d-none');
                fields.classList.remove('d-none');
                submitBtn.disabled = false;
            })
            .catch(function (error) {
                loading.classList.add('d-none');
                showAlert(error.message || 'Error al cargar la cotización.');
            });
    });

    document.getElementById('prEqAddItem').addEventListener('click', function () {
        const opt = productSelect.options[productSelect.selectedIndex];
        if (!opt || !opt.value) {
            return;
        }
        addItemRow({
            product_id: opt.value,
            product_name: opt.textContent,
            quantity: opt.getAttribute('data-qty') || 1,
            unit_price: 0,
            product_description: opt.getAttribute('data-description') || ''
        });
        productSelect.value = '';
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        syncHiddenItems();
        showAlert('');
        submitBtn.disabled = true;
        const formData = new FormData(form);
        fetch(form.getAttribute('action'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
            .then(function (response) {
                return response.json().then(function (payload) {
                    return { ok: response.ok, status: response.status, payload: payload };
                }).catch(function () {
                    return { ok: response.ok, status: response.status, payload: {} };
                });
            })
            .then(function (result) {
                if (result.ok && result.payload && result.payload.redirect) {
                    window.location.href = result.payload.redirect;
                    return;
                }
                if (result.ok) {
                    window.location.reload();
                    return;
                }
                let message = 'No se pudo guardar la cotización.';
                if (result.payload && result.payload.message) {
                    message = result.payload.message;
                } else if (result.payload && result.payload.errors) {
                    const firstKey = Object.keys(result.payload.errors)[0];
                    if (firstKey) {
                        message = result.payload.errors[firstKey][0];
                    }
                }
                showAlert(message);
                submitBtn.disabled = false;
            })
            .catch(function () {
                showAlert('No se pudo guardar la cotización.');
                submitBtn.disabled = false;
            });
    });
})();
</script>
