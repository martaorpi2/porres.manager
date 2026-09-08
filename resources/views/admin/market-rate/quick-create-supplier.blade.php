{{-- Alta mínima de proveedor. Overlay propio en document.body (no Bootstrap modal) para evitar z-index/backdrop bloqueado. --}}
<script>
(function () {
    if (window.__quickCreateSupplierInit) {
        return;
    }
    window.__quickCreateSupplierInit = true;

    var configuredStoreUrl = @json($storeUrl);
    var csrfToken = @json($csrfToken);
    var headings = @json($headings);

    function resolveStoreUrl() {
        var path = window.location.pathname || '';
        var derived = path.replace(/\/market-rate(?:\/.*)?$/, '/api/suppliers');
        if (derived && derived !== path) {
            return derived;
        }
        return configuredStoreUrl;
    }

    function resolveCsrfToken() {
        var fromForm = document.querySelector('input[name="_token"]');
        if (fromForm && fromForm.value) {
            return fromForm.value;
        }
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute('content')) {
            return meta.getAttribute('content');
        }
        return csrfToken;
    }

    function findSupplierSelect() {
        return document.querySelector('select[name="supplier_id"]');
    }

    function ensureButton(select) {
        if (!select || document.getElementById('btnQuickCreateSupplier')) {
            return document.getElementById('btnQuickCreateSupplier');
        }

        var wrap = document.createElement('div');
        wrap.className = 'quick-create-supplier-wrap';
        wrap.style.display = 'flex';
        wrap.style.alignItems = 'stretch';
        wrap.style.gap = '8px';

        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        select.style.flex = '1 1 auto';
        select.style.minWidth = '0';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'btnQuickCreateSupplier';
        btn.className = 'btn btn-outline-primary';
        btn.style.whiteSpace = 'nowrap';
        btn.innerHTML = '<i class="la la-plus"></i> Nuevo';
        btn.setAttribute('aria-haspopup', 'dialog');
        wrap.appendChild(btn);

        return btn;
    }

    function headingOptionsHtml() {
        var html = '<option value="">Seleccione un rubro</option>';
        (headings || []).forEach(function (heading) {
            html += '<option value="' + String(heading.id) + '">' + escapeHtml(heading.name) + '</option>';
        });
        return html;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function buildOverlay() {
        var existing = document.getElementById('quickCreateSupplierOverlay');
        if (existing) {
            existing.parentNode.removeChild(existing);
        }

        var overlay = document.createElement('div');
        overlay.id = 'quickCreateSupplierOverlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'quickCreateSupplierTitle');
        overlay.style.cssText = [
            'display:none',
            'position:fixed',
            'inset:0',
            'z-index:200000',
            'background:rgba(0,0,0,0.45)',
            'align-items:center',
            'justify-content:center',
            'padding:16px',
            'pointer-events:auto'
        ].join(';');

        overlay.innerHTML =
            '<div id="quickCreateSupplierDialog" style="background:#fff;width:100%;max-width:480px;border-radius:6px;box-shadow:0 10px 40px rgba(0,0,0,.25);pointer-events:auto;position:relative;z-index:200001;">' +
                '<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #dee2e6;background:#871f1f;color:#fff;border-radius:6px 6px 0 0;">' +
                    '<h5 id="quickCreateSupplierTitle" style="margin:0;font-size:1rem;">Nuevo proveedor</h5>' +
                    '<button type="button" id="quickCreateSupplierCloseX" aria-label="Cerrar" style="background:transparent;border:0;color:#fff;font-size:22px;line-height:1;cursor:pointer;">&times;</button>' +
                '</div>' +
                '<div style="padding:16px;">' +
                    '<p class="text-muted small mb-3">Alta mínima. El resto de datos se puede completar después en Proveedores.</p>' +
                    '<div id="quickCreateSupplierErrors" class="alert alert-danger d-none" style="display:none;"></div>' +
                    '<div class="form-group mb-3">' +
                        '<label for="quick_supplier_company_name">Nombre <span class="text-danger">*</span></label>' +
                        '<input type="text" id="quick_supplier_company_name" class="form-control" maxlength="255" autocomplete="off">' +
                    '</div>' +
                    '<div class="form-group mb-3">' +
                        '<label for="quick_supplier_cuit">CUIT <span class="text-danger">*</span></label>' +
                        '<input type="text" id="quick_supplier_cuit" class="form-control" maxlength="20" placeholder="Ej: 30-12345678-9" autocomplete="off">' +
                    '</div>' +
                    '<div class="form-group mb-0">' +
                        '<label for="quick_supplier_heading">Rubro <span class="text-danger">*</span></label>' +
                        '<select id="quick_supplier_heading" class="form-control">' + headingOptionsHtml() + '</select>' +
                    '</div>' +
                '</div>' +
                '<div style="display:flex;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid #dee2e6;">' +
                    '<button type="button" class="btn btn-secondary" id="quickCreateSupplierCancel">Cancelar</button>' +
                    '<button type="button" class="btn btn-primary" id="quickCreateSupplierSave">Crear y seleccionar</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                closeOverlay();
            }
        });
        overlay.querySelector('#quickCreateSupplierDialog').addEventListener('click', function (event) {
            event.stopPropagation();
        });
        overlay.querySelector('#quickCreateSupplierCloseX').addEventListener('click', closeOverlay);
        overlay.querySelector('#quickCreateSupplierCancel').addEventListener('click', closeOverlay);
        overlay.querySelector('#quickCreateSupplierSave').addEventListener('click', submitQuickSupplier);

        ['quick_supplier_company_name', 'quick_supplier_cuit'].forEach(function (id) {
            overlay.querySelector('#' + id).addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    event.stopPropagation();
                    submitQuickSupplier();
                }
            });
        });

        return overlay;
    }

    function showErrors(messages) {
        var box = document.getElementById('quickCreateSupplierErrors');
        if (!box) {
            return;
        }
        var list = Array.isArray(messages) ? messages : [String(messages || 'No se pudo crear el proveedor.')];
        box.innerHTML = list.map(function (msg) { return '<div>' + escapeHtml(msg) + '</div>'; }).join('');
        box.style.display = 'block';
        box.classList.remove('d-none');
    }

    function hideErrors() {
        var box = document.getElementById('quickCreateSupplierErrors');
        if (!box) {
            return;
        }
        box.innerHTML = '';
        box.style.display = 'none';
        box.classList.add('d-none');
    }

    function openOverlay() {
        var overlay = buildOverlay();
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        hideErrors();
        setTimeout(function () {
            var nameInput = document.getElementById('quick_supplier_company_name');
            if (nameInput) {
                nameInput.focus();
            }
        }, 30);
    }

    function closeOverlay() {
        var overlay = document.getElementById('quickCreateSupplierOverlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
        document.body.style.overflow = '';
    }

    function collectErrorMessages(payload) {
        var messages = [];
        if (payload && payload.errors) {
            Object.keys(payload.errors).forEach(function (key) {
                (payload.errors[key] || []).forEach(function (msg) {
                    messages.push(msg);
                });
            });
        }
        if (!messages.length && payload && payload.message) {
            messages.push(payload.message);
        }
        return messages;
    }

    function selectCreatedSupplier(id, name) {
        var select = findSupplierSelect();
        if (!select) {
            return;
        }

        var option = Array.prototype.find.call(select.options, function (item) {
            return String(item.value) === String(id);
        });
        if (!option) {
            option = document.createElement('option');
            option.value = String(id);
            option.textContent = name;
            select.appendChild(option);
        }
        option.selected = true;
        select.value = String(id);

        if (window.jQuery) {
            var $select = window.jQuery(select);
            $select.val(String(id)).trigger('change');
        } else {
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function submitQuickSupplier() {
        hideErrors();

        var nameInput = document.getElementById('quick_supplier_company_name');
        var cuitInput = document.getElementById('quick_supplier_cuit');
        var headingInput = document.getElementById('quick_supplier_heading');
        var saveBtn = document.getElementById('quickCreateSupplierSave');

        var companyName = nameInput ? nameInput.value.trim() : '';
        var cuit = cuitInput ? cuitInput.value.trim() : '';
        var headingId = headingInput ? headingInput.value : '';

        if (!companyName || !cuit || !headingId) {
            showErrors('Completá nombre, CUIT y rubro.');
            return;
        }

        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';
        }

        fetch(resolveStoreUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': resolveCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                company_name: companyName,
                cuit: cuit,
                supplier_heading_id: headingId
            })
        }).then(function (response) {
            return response.json().then(function (payload) {
                return { ok: response.ok, status: response.status, payload: payload };
            }).catch(function () {
                return { ok: response.ok, status: response.status, payload: {} };
            });
        }).then(function (result) {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Crear y seleccionar';
            }

            if (!result.ok) {
                var messages = collectErrorMessages(result.payload);
                if (result.status === 419) {
                    messages = ['La sesión expiró. Recargá la página e intentá de nuevo.'];
                }
                showErrors(messages.length ? messages : 'No se pudo crear el proveedor.');
                return;
            }

            selectCreatedSupplier(result.payload.id, result.payload.company_name);
            closeOverlay();
        }).catch(function () {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Crear y seleccionar';
            }
            showErrors('No se pudo crear el proveedor. Revisá la conexión e intentá de nuevo.');
        });
    }

    function onEscape(event) {
        if (event.key !== 'Escape') {
            return;
        }
        var overlay = document.getElementById('quickCreateSupplierOverlay');
        if (overlay && overlay.style.display === 'flex') {
            event.preventDefault();
            closeOverlay();
        }
    }

    var wired = false;

    function init() {
        if (wired) {
            return;
        }
        var select = findSupplierSelect();
        if (!select) {
            return;
        }
        var btn = ensureButton(select);
        if (!btn) {
            return;
        }
        wired = true;
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            openOverlay();
        });
        document.addEventListener('keydown', onEscape);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    setTimeout(init, 150);
})();
</script>
