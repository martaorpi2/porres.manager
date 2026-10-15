@php
    $reconciliationCount = 0;
    $reconciliationCards = [];
    foreach ($accreditationAmountAlerts as $group) {
        $reconciliationCount += (int) $group['count'];
        foreach ($group['amounts'] as $row) {
            $reconciliationCards[] = [
                'menu' => $group['menu'],
                'border' => '#dc3545',
                'entries' => $row['entries'] ?? [],
                'orders' => $row['orders'] ?? [],
                'missing' => ! empty($row['missing_collection']),
                'blocks' => [
                    ['icon' => 'la-calendar', 'text' => $row['when']],
                    ['parts' => ['Cobranza: '.$row['debe'], 'Acreditación: '.$row['haber']]],
                    ['icon' => 'la-exchange-alt', 'text' => 'Diferencia: '.$row['difference'], 'color' => '#dc3545'],
                ],
            ];
        }
        foreach ($group['missing'] as $missing) {
            $reconciliationCards[] = [
                'menu' => $group['menu'],
                'border' => '#ffc107',
                'entries' => $missing['entries'] ?? [],
                'orders' => $missing['orders'] ?? [],
                'missing' => true,
                'blocks' => [
                    ['icon' => 'la-exclamation-triangle', 'text' => 'Asiento de cobranza faltante', 'color' => '#856404'],
                    ['text' => $missing['text']],
                ],
            ];
        }
        foreach ($group['excluded'] ?? [] as $excluded) {
            $reconciliationCards[] = [
                'menu' => $group['menu'],
                'border' => '#ffc107',
                'entries' => $excluded['entries'] ?? [],
                'orders' => $excluded['orders'] ?? [],
                'missing' => false,
                'blocks' => [
                    ['icon' => 'la-exclamation-triangle', 'text' => 'No entra al asiento', 'color' => '#856404'],
                    ['text' => $excluded['text']],
                ],
            ];
        }
        foreach ($group['mixed'] ?? [] as $mixed) {
            $reconciliationCards[] = [
                'menu' => $group['menu'],
                'border' => '#871f1f',
                'entries' => $mixed['entries'] ?? [],
                'orders' => $mixed['orders'] ?? [],
                'missing' => ! empty($mixed['missing_collection']),
                'blocks' => [
                    ['icon' => 'la-calendar', 'text' => 'Varias fechas de cobro', 'color' => '#721c24'],
                    ['text' => $mixed['text']],
                ],
            ];
        }
    }
@endphp
@once
<style>
    .reconciliation-alert .process-item-card { cursor: pointer; }
    #reconciliationEntriesModal { z-index: 10850 !important; }
    #reconciliationEntriesModal .modal-dialog {
        z-index: 10851;
        max-width: min(1200px, calc(100vw - 2rem));
    }
    #reconciliationEntriesModal .modal-content { pointer-events: auto; }
    #reconciliationEntriesModal .modal-body { color: #1e2a4a; }
    #reconciliationEntriesModalSummary {
        color: #ffe08a !important;
        font-weight: 600;
        margin: 0.35rem 0 0;
    }
    #reconciliationEntriesModal .reconciliation-modal-close {
        background: transparent !important;
        border: 0;
        color: #fff !important;
        font-size: 1.75rem;
        line-height: 1;
        margin-left: auto;
        padding: 0 0.15rem;
        cursor: pointer;
    }
    .modal-backdrop.reconciliation-backdrop { z-index: 10840 !important; }
</style>
@endonce
<div class="row mb-4">
    <div class="col-md-12">
        <h3 class="section-title mb-0">Inconsistencias en la conciliación de cobranzas y acreditaciones</h3>
        <p class="mb-0 mt-2" style="color:#000;">Se detectaron diferencias entre los asientos de cobranza y las acreditaciones registradas por medio de pago. Revise los siguientes casos.</p>
    </div>
</div>
<div id="accreditation-reconciliation-section" class="process-step" style="border-left: 4px solid #dc3545;">
    <div class="process-step-header" style="background-color: #f8d7da;">
        <div class="process-step-title">
            <i class="la la-balance-scale process-step-icon" style="color: #721c24;"></i>
            <span style="color: #721c24; font-weight: bold;">Cobranzas y acreditaciones</span>
        </div>
        <span class="process-step-count" style="background-color: #dc3545; color: white;">{{ $reconciliationCount }}</span>
    </div>
    <div class="process-step-content">
        @foreach($reconciliationCards as $card)
            @php
                $params = array_filter([
                    'entries' => $card['entries'] === [] ? null : implode(',', $card['entries']),
                    'orders' => $card['orders'] === [] ? null : implode(',', $card['orders']),
                    'missing' => $card['missing'] ? 1 : null,
                ], fn ($value) => $value !== null && $value !== '');
                $detailUrl = backpack_url('accounting-reconciliation/entries').($params === [] ? '' : '?'.http_build_query($params));
            @endphp
            <div class="reconciliation-alert">
                <div class="process-item-card" data-reconciliation-card data-url="{{ $detailUrl }}" data-title="{{ $card['menu'] }}" data-summary="{{ collect($card['blocks'])->map(fn ($block) => implode(' · ', $block['parts'] ?? [$block['text']]))->implode(' · ') }}" tabindex="0" role="button" aria-haspopup="dialog" aria-controls="reconciliationEntriesModal" style="border-left: 3px solid {{ $card['border'] }};">
                    <div class="process-item-title">{{ $card['menu'] }}</div>
                    @foreach($card['blocks'] as $block)
                        <div class="process-item-meta">
                            @foreach($block['parts'] ?? [$block['text']] as $part)
                                <span @if(!empty($block['color'])) style="color: {{ $block['color'] }}; font-weight: bold;" @endif>
                                    @if(!empty($block['icon']) && $loop->first)<i class="la {{ $block['icon'] }}"></i> @endif{{ $part }}
                                </span>
                            @endforeach
                        </div>
                    @endforeach
                    <div class="process-item-meta">
                        <span style="font-weight: bold;">Ver asientos</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
<div class="modal fade" id="reconciliationEntriesModal" tabindex="-1" aria-labelledby="reconciliationEntriesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="reconciliationEntriesModalLabel">Asientos</h5>
                    <p class="mb-0" id="reconciliationEntriesModalSummary"></p>
                </div>
                <button type="button" class="reconciliation-modal-close" data-bs-dismiss="modal" data-reconciliation-close aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body">
                <div id="reconciliationEntriesModalBody"></div>
            </div>
        </div>
    </div>
</div>
@push('after_scripts')
<script>
function bootReconciliationModal() {
    var modalEl = document.getElementById('reconciliationEntriesModal');
    if (!modalEl || modalEl.getAttribute('data-ready') === '1') {
        return;
    }
    modalEl.setAttribute('data-ready', '1');
    document.body.appendChild(modalEl);

    var modal = null;
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true, focus: true });
        modalEl.addEventListener('shown.bs.modal', function () {
            var backdrops = document.querySelectorAll('.modal-backdrop');
            var backdrop = backdrops[backdrops.length - 1];
            if (backdrop) {
                backdrop.classList.add('reconciliation-backdrop');
            }
        });
    }

    var body = document.getElementById('reconciliationEntriesModalBody');
    var title = document.getElementById('reconciliationEntriesModalLabel');
    var summary = document.getElementById('reconciliationEntriesModalSummary');
    var loadedUrl = '';

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-reconciliation-close]')) {
            event.preventDefault();
            hideModal();
            return;
        }
        var button = event.target.closest('[data-coupons-url]');
        if (button) {
            event.preventDefault();
            toggleCoupons(button);
            return;
        }
        var card = event.target.closest('[data-reconciliation-card]');
        if (!card) {
            return;
        }
        openAlert(card);
    });
    document.addEventListener('keydown', function (event) {
        if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('[data-reconciliation-card]')) {
            event.preventDefault();
            event.target.click();
            return;
        }
        if (event.key === 'Escape' && modalEl.classList.contains('show')) {
            hideModal();
        }
    });

    function showModal() {
        if (modal) {
            modal.show();
            return;
        }
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        modalEl.removeAttribute('aria-hidden');
        modalEl.setAttribute('aria-modal', 'true');
        document.body.classList.add('modal-open');
        if (!document.querySelector('.reconciliation-backdrop')) {
            var backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show reconciliation-backdrop';
            document.body.appendChild(backdrop);
            backdrop.addEventListener('click', hideModal);
        }
    }

    function hideModal() {
        if (modal) {
            modal.hide();
            return;
        }
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        document.querySelectorAll('.reconciliation-backdrop').forEach(function (el) {
            el.remove();
        });
    }

    function openAlert(card) {
        var url = card.getAttribute('data-url');
        title.textContent = 'Asientos de ' + (card.getAttribute('data-title') || '');
        summary.textContent = card.getAttribute('data-summary') || '';
        summary.hidden = summary.textContent === '';
        showModal();
        if (loadedUrl === url && body.getAttribute('data-loaded') === '1') {
            return;
        }
        loadedUrl = url;
        body.removeAttribute('data-loaded');
        body.innerHTML = '<p>Cargando asientos…</p>';
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }, credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error();
                }
                return response.text();
            })
            .then(function (html) {
                if (loadedUrl !== url) {
                    return;
                }
                body.innerHTML = html;
                body.setAttribute('data-loaded', '1');
            })
            .catch(function () {
                if (loadedUrl === url) {
                    body.innerHTML = '<p>No se pudieron cargar los asientos.</p>';
                }
            });
    }

    function toggleCoupons(button) {
        var panel = button.nextElementSibling;
        if (!panel) {
            return;
        }
        var open = panel.hidden;
        panel.hidden = !open;
        button.textContent = open ? 'Ocultar cupones' : 'Ver cupones';
        if (!open || panel.getAttribute('data-loaded') === '1') {
            return;
        }
        panel.innerHTML = '<p>Cargando cupones…</p>';
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
                panel.innerHTML = '<p>No se pudieron cargar los cupones.</p>';
            });
    }
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootReconciliationModal);
} else {
    bootReconciliationModal();
}
</script>
@endpush
