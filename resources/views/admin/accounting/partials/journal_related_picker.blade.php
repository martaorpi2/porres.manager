@php
    $relatedEntries = $relatedEntries ?? collect();
    $paymentType = $paymentType ?? null;
    $excludeId = $excludeId ?? null;
@endphp
<div
    class="mb-3"
    id="related-entries"
    data-search-url="{{ backpack_url('accounting-journal/search') }}"
    data-payment-type="{{ $paymentType }}"
    data-exclude="{{ $excludeId }}"
>
    <label for="related-search" class="journal-label">Asientos relacionados</label>
    <input type="hidden" name="related_ids_present" value="1">
    <input type="text" id="related-search" class="form-control" placeholder="Número, fecha o descripción" autocomplete="off">
    <p class="related-hint mb-0">Elegí un asiento y seguí buscando: se pueden relacionar varios.</p>
    <div id="related-results" hidden></div>
    @error('related_ids')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    <ul id="related-selected" class="related-selected">
        @foreach($relatedEntries as $related)
            <li class="related-entry">
                <input type="hidden" name="related_ids[]" value="{{ $related->id }}">
                <span>{{ $related->relationLabel() }}</span>
                <button type="button" class="btn journal-btn-remove related-remove">Quitar</button>
            </li>
        @endforeach
    </ul>
</div>

@once
<style>
    #related-entries { position: relative; max-width: 42rem; }
    .related-hint {
        margin-top: 0.35rem;
        color: #5c6578;
        font-size: 0.85rem;
    }
    #related-results {
        position: relative;
        z-index: 30;
        margin-top: 0.35rem;
        background: #fff;
        border: 1px solid #e6e8ee;
        border-radius: 6px;
        box-shadow: 0 8px 20px rgba(16, 24, 40, 0.08);
        max-height: 16rem;
        overflow: auto;
    }
    #related-results[hidden] { display: none; }
    .related-option {
        display: block;
        width: 100%;
        text-align: left;
        background: #fff;
        border: none;
        border-bottom: 1px solid #eef0f4;
        color: #1e2a4a;
        padding: 0.55rem 0.75rem;
        font-size: 0.9rem;
    }
    .related-option:hover { background: #fdf6f6; }
    .related-empty {
        margin: 0;
        padding: 0.55rem 0.75rem;
        color: #5c6578;
    }
    .related-selected {
        list-style: none;
        margin: 0.55rem 0 0;
        padding: 0;
    }
    .related-entry {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.45rem 0;
        border-bottom: 1px solid #e6e8ee;
        color: #1e2a4a;
    }
    .related-entry span { font-size: 0.92rem; }
    .related-selected:empty { display: none; }
</style>
@endonce

<script>
    (function () {
        var root = document.getElementById('related-entries');
        if (!root || root.getAttribute('data-ready') === '1') return;
        root.setAttribute('data-ready', '1');

        var input = document.getElementById('related-search');
        var results = document.getElementById('related-results');
        var selected = document.getElementById('related-selected');
        var url = root.getAttribute('data-search-url');
        var paymentType = root.getAttribute('data-payment-type') || '';
        var exclude = root.getAttribute('data-exclude') || '';
        var timer = null;

        function selectedIds() {
            return Array.prototype.map.call(selected.querySelectorAll('input[name="related_ids[]"]'), function (field) {
                return field.value;
            });
        }

        function addEntry(item) {
            var id = String(parseInt(item.id, 10));
            if (!id || id === 'NaN' || selectedIds().indexOf(id) !== -1) return;
            var row = document.createElement('li');
            row.className = 'related-entry';
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'related_ids[]';
            hidden.value = id;
            var label = document.createElement('span');
            label.textContent = item.label || '';
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn journal-btn-remove related-remove';
            button.textContent = 'Quitar';
            row.appendChild(hidden);
            row.appendChild(label);
            row.appendChild(button);
            selected.appendChild(row);
        }

        function showResults(items) {
            var chosen = selectedIds();
            var pending = items.filter(function (item) {
                return chosen.indexOf(String(item.id)) === -1;
            });
            results.innerHTML = '';
            results.hidden = false;
            if (pending.length === 0) {
                var empty = document.createElement('p');
                empty.className = 'related-empty';
                empty.textContent = 'No hay asientos para esa búsqueda.';
                results.appendChild(empty);
                return;
            }
            pending.forEach(function (item) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'related-option';
                button.textContent = item.label || '';
                button.addEventListener('click', function () {
                    addEntry(item);
                    results.hidden = true;
                    results.innerHTML = '';
                    input.value = '';
                    input.focus();
                });
                results.appendChild(button);
            });
        }

        function fetchResults(term) {
            if (term.length < 2 && paymentType === '') {
                results.hidden = true;
                results.innerHTML = '';
                return;
            }
            var params = new URLSearchParams();
            if (term !== '') params.set('q', term);
            if (paymentType !== '') params.set('payment_type', paymentType);
            if (exclude !== '') params.set('exclude', exclude);
            fetch(url + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                .then(function (response) {
                    if (!response.ok) throw new Error('search');
                    return response.json();
                })
                .then(showResults)
                .catch(function () {
                    results.hidden = false;
                    results.innerHTML = '';
                    var empty = document.createElement('p');
                    empty.className = 'related-empty';
                    empty.textContent = 'No se pudo buscar asientos.';
                    results.appendChild(empty);
                });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var term = input.value.trim();
            timer = setTimeout(function () { fetchResults(term); }, 250);
        });
        input.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            var option = results.querySelector('.related-option');
            if (option) option.click();
        });
        input.addEventListener('focus', function () {
            if (input.value.trim() === '' && paymentType !== '') fetchResults('');
        });
        selected.addEventListener('click', function (event) {
            var button = event.target.closest('.related-remove');
            if (!button) return;
            button.closest('.related-entry').remove();
        });
        document.addEventListener('click', function (event) {
            if (!root.contains(event.target)) results.hidden = true;
        });
    })();
</script>
