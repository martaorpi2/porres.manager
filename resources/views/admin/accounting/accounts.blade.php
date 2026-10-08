@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="mb-0 journal-title" bp-section="page-heading">Cuentas</h1>
        <a href="{{ $pdfUrl }}" class="btn btn-sm accounts-pdf" target="_blank">Exportar PDF</a>
    </section>
@endsection

@section('content')
<div class="row accounts-page">
    <div class="col-lg-6 mb-3">
        <div class="card accounts-card">
            <div class="card-body">
                <div class="accounts-tree-head">
                    <label for="account-search" class="journal-label mb-0">Plan de cuentas</label>
                    @if($canCreate)
                        <button type="button" class="accounts-text-action" id="account-add-rubro">Agregar rubro</button>
                    @endif
                </div>
                <input type="search" id="account-search" class="form-control mb-3" placeholder="Buscar por código o nombre" autocomplete="off">
                <div class="accounts-tree-scroll" id="account-tree">
                    @include('admin.accounting.partials.account_tree', ['nodes' => $tree])
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card accounts-card accounts-form-card">
            <div class="card-body">
                <h2 class="accounts-action-title" id="account-action" hidden></h2>

                <p class="accounts-hint" id="account-hint">Hacé clic en un rubro o una cuenta del árbol para agregar o modificar.</p>
                <p class="accounts-context" id="account-context" hidden></p>
                <p class="accounts-actions" id="account-actions" hidden>
                    <button type="button" class="accounts-text-action" id="account-add-subrubro">Agregar subrubro</button>
                    <button type="button" class="accounts-text-action" id="account-add-child">Agregar cuenta</button>
                    <button type="button" class="accounts-text-action" id="account-remove-open">Quitar</button>
                </p>

                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="post" action="{{ $saveUrl }}" id="account-form" hidden>
                    @csrf
                    <input type="hidden" name="id" id="account-id" value="{{ old('id') }}">
                    <input type="hidden" name="parent_code" id="account-parent" value="{{ old('parent_code') }}">
                    <input type="hidden" name="is_grouping" id="account-grouping" value="{{ old('is_grouping', '0') }}">

                    <div class="mb-3">
                        <label for="account-code" class="journal-label">Código</label>
                        <input type="text" name="code" id="account-code" class="form-control" maxlength="30" required value="{{ old('code') }}" placeholder="Ej: 11101900">
                        <p class="accounts-code-note" id="account-code-note" hidden>Si cambiás el código, las cuentas hijas se actualizan para seguir en este rubro.</p>
                    </div>
                    <div class="mb-3">
                        <label for="account-name" class="journal-label">Nombre</label>
                        <input type="text" name="name" id="account-name" class="form-control" maxlength="255" required value="{{ old('name') }}" placeholder="Ej: Útiles y papelería">
                    </div>
                    <div class="mb-3">
                        <label for="account-type" class="journal-label">Tipo</label>
                        <select name="account_type" id="account-type" class="form-control">
                            <option value="">Sin tipo</option>
                            @foreach($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('account_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3" id="account-balance-wrap">
                        <label for="account-balance" class="journal-label">Tipo de saldo</label>
                        <select name="balance_nature" id="account-balance" class="form-control" required>
                            <option value="">Elegir</option>
                            @foreach($balanceNatures as $value => $label)
                                <option value="{{ $value }}" @selected(old('balance_nature') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="account-active" class="form-check-input" value="1" @checked(old('is_active', '1') == '1')>
                        <label for="account-active" class="form-check-label">Activa</label>
                    </div>
                    <button type="submit" class="btn accounts-save" id="account-save">Guardar</button>
                </form>

                <form method="post" action="{{ $removeUrl }}" id="account-remove" hidden>
                    @csrf
                    <input type="hidden" name="id" id="remove-id">
                    <input type="hidden" name="children_action" id="remove-action-none" value="none">
                    <p class="accounts-context" id="remove-summary"></p>
                    <div id="remove-choices">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="children_action" id="remove-action-move" value="move" checked>
                            <label class="form-check-label" for="remove-action-move">Mover las cuentas hijas a otro padre</label>
                        </div>
                        <div class="mb-3" id="remove-target-wrap">
                            <label for="remove-target" class="journal-label">Padre destino</label>
                            <select name="target_parent_code" id="remove-target" class="form-control"></select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="children_action" id="remove-action-delete" value="delete">
                            <label class="form-check-label" for="remove-action-delete">Eliminar también las cuentas hijas</label>
                        </div>
                    </div>
                    <button type="submit" class="btn accounts-save">Confirmar</button>
                    <button type="button" class="accounts-text-action" id="remove-cancel">Cancelar</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('after_styles')
<style>
    .journal-title { color: #1e2a4a; font-weight: 600; }
    .accounts-pdf {
        margin-left: auto;
        background: #fff;
        border: 1px solid #871f1f;
        color: #871f1f !important;
        font-weight: 600;
    }
    .accounts-pdf:hover { background: #871f1f; color: #fff !important; }
    .accounts-card {
        background: #fff;
        border: 1px solid #e6e8ee;
        border-left: 4px solid #871f1f !important;
        border-radius: 6px;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
    }
    .accounts-tree-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.65rem;
    }
    #account-search {
        display: block;
        width: 100%;
        background-color: #f4f6f8;
        color: #1e2a4a;
        border: 1px solid #98a2b3;
        border-radius: 6px;
        box-shadow: inset 0 1px 2px rgba(16, 24, 40, 0.06);
        -webkit-appearance: none;
        appearance: none;
    }
    #account-search::placeholder {
        color: #667085;
        opacity: 1;
    }
    #account-search:focus {
        background-color: #fff;
        color: #1e2a4a;
        border-color: #871f1f;
        box-shadow: 0 0 0 0.2rem rgba(135, 31, 31, 0.15);
        outline: none;
    }
    .journal-label {
        font-weight: 700;
        color: #1e2a4a;
        font-size: 0.95rem;
        margin-bottom: 0.35rem;
    }
    .accounts-tree-scroll {
        max-height: calc(100vh - 240px);
        overflow: auto;
        padding-right: 0.25rem;
    }
    .accounts-form-card { position: sticky; top: 12px; }
    .account-tree, .account-tree ul { list-style: none; margin: 0; padding-left: 0.85rem; }
    .account-tree { padding-left: 0; }
    .account-row { display: flex; align-items: flex-start; gap: 0.15rem; }
    .account-toggle, .account-toggle-spacer {
        width: 1.4rem;
        height: 1.7rem;
        flex: 0 0 1.4rem;
        border: 0;
        background: transparent;
        color: #871f1f;
        padding: 0;
        line-height: 1.7rem;
    }
    .account-toggle { cursor: pointer; }
    .account-pick {
        flex: 1;
        text-align: left;
        border: 0;
        background: transparent;
        padding: 0.2rem 0.35rem;
        border-radius: 4px;
        color: #1e2a4a;
        line-height: 1.35;
    }
    .account-pick:hover, .account-node.is-selected > .account-row .account-pick { background: #f8ecec; }
    .account-node.is-group > .account-row .account-name { font-weight: 700; color: #871f1f; }
    .account-code {
        display: inline-block;
        min-width: 5.6rem;
        font-variant-numeric: tabular-nums;
        font-weight: 600;
        margin-right: 0.35rem;
    }
    .account-node.is-inactive > .account-row .account-pick { color: #98a2b3; }
    .account-badge {
        margin-left: 0.4rem;
        font-size: 0.7rem;
        font-weight: 700;
        color: #871f1f;
        border: 1px solid #871f1f;
        border-radius: 999px;
        padding: 0 0.4rem;
    }
    .account-badge-nature {
        color: #475467;
        border-color: #d0d5dd;
        font-weight: 600;
    }
    .account-node.is-filter-hidden { display: none; }
    .accounts-action-title {
        color: #1e2a4a;
        font-size: 1.35rem;
        font-weight: 600;
        margin: 0 0 0.35rem;
    }
    .accounts-actions { display: flex; flex-wrap: wrap; gap: 0.75rem 1rem; margin: 0 0 0.85rem; }
    .accounts-text-action {
        background: none;
        border: 0;
        padding: 0;
        color: #871f1f;
        font-weight: 600;
        text-decoration: underline;
    }
    .accounts-code-note { color: #475467; font-size: 0.85rem; margin: 0.35rem 0 0; }
    .accounts-hint, .accounts-context { color: #475467; margin-bottom: 0.85rem; }
    .accounts-context { font-weight: 600; color: #1e2a4a; }
    .accounts-save {
        background: #871f1f;
        border-color: #871f1f;
        color: #fff;
        font-weight: 600;
    }
    .accounts-save:hover { background: #6e1919; color: #fff; }
</style>
@endsection

@section('after_scripts')
<script>
(function () {
    var tree = document.getElementById('account-tree');
    var form = document.getElementById('account-form');
    var hint = document.getElementById('account-hint');
    var context = document.getElementById('account-context');
    var actionTitle = document.getElementById('account-action');
    var canCreate = @json($canCreate);
    var canUpdate = @json($canUpdate);
    var balanceByType = @json($balanceByType);
    var selectedCode = @json($selectedCode);
    var oldForm = @json($oldForm);
    var selected = null;

    function nodeData(li) {
        return {
            id: li.getAttribute('data-id') || '',
            code: li.getAttribute('data-code') || '',
            name: li.getAttribute('data-name') || '',
            parent: li.getAttribute('data-parent') || '',
            type: li.getAttribute('data-type') || '',
            active: li.getAttribute('data-active') === '1',
            receives: li.getAttribute('data-receives') === '1',
            grouping: li.getAttribute('data-grouping') === '1',
            balanceNature: li.getAttribute('data-balance-nature') || ''
        };
    }

    function balanceFromType(type) {
        return balanceByType[type] || '';
    }

    function suggestRootCode() {
        var roots = tree.querySelectorAll(':scope > .account-tree > .account-node');
        var max = 0;
        var width = 8;
        Array.prototype.forEach.call(roots, function (node) {
            var code = node.getAttribute('data-code') || '';
            if (!/^\d+$/.test(code)) return;
            var value = parseInt(code, 10);
            if (value > max) {
                max = value;
                width = code.length;
            }
        });
        if (!max) return '';
        var step = Math.pow(10, Math.max(width - 1, 1));
        var next = String((Math.floor(max / step) + 1) * step);
        while (next.length < width) next = '0' + next;
        return next;
    }

    function setOpen(li, open) {
        var box = li.querySelector(':scope > .account-children');
        var toggle = li.querySelector(':scope > .account-row .account-toggle');
        if (!box || !toggle) return;
        box.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        var icon = toggle.querySelector('i');
        if (icon) icon.className = open ? 'la la-angle-down' : 'la la-angle-right';
    }

    function expandTo(li) {
        var parent = li.parentElement;
        while (parent && parent !== tree) {
            if (parent.classList && parent.classList.contains('account-children')) {
                var owner = parent.parentElement;
                if (owner && owner.classList.contains('account-node')) setOpen(owner, true);
            }
            parent = parent.parentElement;
        }
    }

    function findByCode(code) {
        if (!code) return null;
        return tree.querySelector('.account-node[data-code="' + CSS.escape(code) + '"]');
    }

    function childCodes(li) {
        var box = li.querySelector(':scope > .account-children');
        if (!box) return [];
        return Array.prototype.map.call(box.querySelectorAll(':scope > .account-tree > .account-node'), function (child) {
            return child.getAttribute('data-code') || '';
        }).filter(Boolean);
    }

    function codeStep(a, b) {
        var x = Math.abs(a);
        var y = Math.abs(b);
        while (y) {
            var rest = x % y;
            x = y;
            y = rest;
        }
        return x || 1;
    }

    function suggestCode(parentCode, codes) {
        var same = codes.filter(function (code) {
            return code.length === parentCode.length && /^\d+$/.test(code);
        }).sort(function (a, b) { return parseInt(a, 10) - parseInt(b, 10); });
        if (!same.length || !/^\d+$/.test(parentCode)) return '';
        var last = parseInt(same[same.length - 1], 10);
        var step = 1;
        if (same.length >= 2) {
            step = parseInt(same[1], 10) - parseInt(same[0], 10);
            for (var i = 2; i < same.length; i++) {
                step = codeStep(step, parseInt(same[i], 10) - parseInt(same[i - 1], 10));
            }
            if (step <= 0) step = 1;
        } else {
            var only = last - parseInt(parentCode, 10);
            if (only > 0) {
                var zeros = String(only).match(/0+$/);
                step = zeros ? Math.pow(10, zeros[0].length) : 1;
            }
        }
        var next = String(last + step);
        while (next.length < parentCode.length) next = '0' + next;
        return next;
    }

    function typeFromCode(code) {
        return { '1': 'activo', '2': 'pasivo', '3': 'patrimonio', '4': 'ingreso', '5': 'gasto' }[(code || '').charAt(0)] || '';
    }

    function setActionTitle(mode) {
        var grouping = document.getElementById('account-grouping').value === '1';
        if (mode === 'create-rubro') {
            actionTitle.hidden = false;
            actionTitle.textContent = 'Agregar rubro';
            return;
        }
        if (mode === 'create-subrubro') {
            actionTitle.hidden = false;
            actionTitle.textContent = 'Agregar subrubro';
            return;
        }
        if (mode === 'create') {
            actionTitle.hidden = false;
            actionTitle.textContent = 'Agregar cuenta';
            return;
        }
        if (mode === 'edit') {
            actionTitle.hidden = false;
            actionTitle.textContent = grouping ? 'Modificar rubro' : 'Modificar';
            return;
        }
        if (mode === 'remove') {
            actionTitle.hidden = false;
            actionTitle.textContent = 'Quitar';
            return;
        }
        actionTitle.hidden = true;
        actionTitle.textContent = '';
    }

    function descendantCount(li) {
        return li.querySelectorAll('.account-node').length;
    }

    function syncParentActions(li, mode) {
        var actions = document.getElementById('account-actions');
        var add = document.getElementById('account-add-child');
        var sub = document.getElementById('account-add-subrubro');
        var remove = document.getElementById('account-remove-open');
        var note = document.getElementById('account-code-note');
        if (!li) {
            actions.hidden = true;
            note.hidden = true;
            document.getElementById('account-remove').hidden = true;
            return;
        }
        var descendants = descendantCount(li);
        var grouping = li.getAttribute('data-grouping') === '1';
        var leaf = !grouping && descendants === 0;
        var canAdd = (mode === 'edit' || mode === 'view') && canCreate && !leaf;
        var showRemove = mode === 'edit' && canUpdate && !!li.getAttribute('data-id');
        add.hidden = !canAdd;
        sub.hidden = !canAdd;
        remove.hidden = !showRemove;
        actions.hidden = add.hidden && sub.hidden && remove.hidden;
        note.hidden = mode !== 'edit' || descendants === 0;
        document.getElementById('account-remove').hidden = true;
    }

    function fillForm(values, mode) {
        document.getElementById('account-id').value = values.id || '';
        document.getElementById('account-parent').value = values.parent || '';
        document.getElementById('account-code').value = values.code || '';
        document.getElementById('account-name').value = values.name || '';
        document.getElementById('account-type').value = values.type || '';
        document.getElementById('account-grouping').value = values.grouping ? '1' : '0';
        var balance = document.getElementById('account-balance');
        balance.value = values.balance || '';
        balance.dataset.touched = mode === 'edit' ? '1' : '0';
        document.getElementById('account-balance-wrap').hidden = !!values.grouping;
        balance.required = !values.grouping;
        document.getElementById('account-active').checked = values.active !== false && values.active !== '0';
        var locked = mode === 'edit' && !canUpdate;
        balance.disabled = !!values.grouping || locked;
        ['account-code', 'account-name', 'account-type', 'account-active'].forEach(function (id) {
            document.getElementById(id).disabled = locked;
        });
        document.getElementById('account-save').hidden = locked;
        form.hidden = false;
        document.getElementById('account-remove').hidden = true;
        setActionTitle(mode);
    }

    function showCreate(li, kind) {
        var data = nodeData(li);
        var grouping = kind === 'subrubro';
        var parentType = data.receives ? data.type : typeFromCode(data.code);
        var parentLabel = data.code + ' ' + data.name;
        hint.hidden = true;
        context.hidden = false;
        context.textContent = (grouping ? 'Subrubro dentro de ' : 'Dentro de ') + parentLabel;
        fillForm({
            id: '',
            parent: data.code,
            code: suggestCode(data.code, childCodes(li)),
            name: '',
            type: parentType,
            active: true,
            grouping: grouping,
            balance: grouping ? '' : (data.balanceNature || balanceFromType(parentType))
        }, grouping ? 'create-subrubro' : 'create');
        syncParentActions(li, grouping ? 'create-subrubro' : 'create');
        document.getElementById('account-name').focus();
    }

    function showCreateRubro() {
        tree.querySelectorAll('.account-node.is-selected').forEach(function (node) {
            node.classList.remove('is-selected');
        });
        selected = null;
        hint.hidden = true;
        context.hidden = false;
        context.textContent = 'Rubro de primer nivel.';
        fillForm({
            id: '',
            parent: '',
            code: suggestRootCode(),
            name: '',
            type: '',
            active: true,
            grouping: true,
            balance: ''
        }, 'create-rubro');
        syncParentActions(null, 'create-rubro');
        document.getElementById('account-name').focus();
    }

    function showEdit(li) {
        var data = nodeData(li);
        hint.hidden = true;
        context.hidden = false;
        if (!data.id) {
            context.textContent = data.code + ' ' + data.name + ' es un rubro de agrupación. Agregá un subrubro o una cuenta debajo.';
            form.hidden = true;
            setActionTitle('');
            syncParentActions(li, canCreate ? 'view' : '');
            return;
        }
        context.textContent = data.code + ' ' + data.name;
        fillForm({
            id: data.id,
            parent: data.parent,
            code: data.code,
            name: data.name,
            type: data.type,
            active: data.active,
            grouping: data.grouping,
            balance: data.grouping ? '' : (data.balanceNature || balanceFromType(data.type))
        }, 'edit');
        syncParentActions(li, 'edit');
    }

    function fillTargets(li) {
        var select = document.getElementById('remove-target');
        var banned = {};
        banned[li.getAttribute('data-code')] = true;
        li.querySelectorAll('.account-node').forEach(function (node) {
            banned[node.getAttribute('data-code')] = true;
        });
        select.innerHTML = '';
        tree.querySelectorAll('.account-node').forEach(function (node) {
            var code = node.getAttribute('data-code');
            if (banned[code]) return;
            var option = document.createElement('option');
            option.value = code;
            option.textContent = code + ' ' + (node.getAttribute('data-name') || '');
            select.appendChild(option);
        });
    }

    function openRemove() {
        if (!selected) return;
        var data = nodeData(selected);
        var count = descendantCount(selected);
        form.hidden = true;
        document.getElementById('account-actions').hidden = true;
        hint.hidden = true;
        context.hidden = true;
        document.getElementById('account-code-note').hidden = true;
        setActionTitle('remove');
        var panel = document.getElementById('account-remove');
        panel.hidden = false;
        document.getElementById('remove-id').value = data.id;
        var choices = document.getElementById('remove-choices');
        var summary = document.getElementById('remove-summary');
        var none = document.getElementById('remove-action-none');
        var move = document.getElementById('remove-action-move');
        var wipe = document.getElementById('remove-action-delete');
        var target = document.getElementById('remove-target');
        if (count === 0) {
            choices.hidden = true;
            none.disabled = false;
            move.disabled = true;
            wipe.disabled = true;
            target.disabled = true;
            summary.textContent = 'Se va a quitar ' + data.code + ' ' + data.name + '.';
            return;
        }
        choices.hidden = false;
        none.disabled = true;
        move.disabled = false;
        wipe.disabled = false;
        target.disabled = wipe.checked;
        summary.textContent = 'Hay ' + count + (count === 1 ? ' cuenta que cuelga' : ' cuentas que cuelgan') + ' de este rubro. Antes de quitarlo, elegí qué hacer con ellas.';
        fillTargets(selected);
    }

    function selectNode(li, mode) {
        tree.querySelectorAll('.account-node.is-selected').forEach(function (node) {
            node.classList.remove('is-selected');
        });
        li.classList.add('is-selected');
        selected = li;
        expandTo(li);
        if (mode === 'create' || !li.getAttribute('data-id')) {
            if (canCreate) showCreate(li);
            else showEdit(li);
            return;
        }
        showEdit(li);
    }

    tree.addEventListener('click', function (event) {
        var toggle = event.target.closest('.account-toggle');
        if (toggle) {
            var li = toggle.closest('.account-node');
            var box = li.querySelector(':scope > .account-children');
            setOpen(li, box.hidden);
            return;
        }
        var pick = event.target.closest('.account-pick');
        if (!pick) return;
        var node = pick.closest('.account-node');
        var box = node.querySelector(':scope > .account-children');
        if (box && box.hidden) setOpen(node, true);
        selectNode(node, node.getAttribute('data-id') ? 'edit' : 'create');
    });

    document.getElementById('account-add-child').addEventListener('click', function () {
        if (!selected || !canCreate || this.hidden) return;
        showCreate(selected, 'cuenta');
    });
    document.getElementById('account-add-subrubro').addEventListener('click', function () {
        if (!selected || !canCreate || this.hidden) return;
        showCreate(selected, 'subrubro');
    });
    var addRubro = document.getElementById('account-add-rubro');
    if (addRubro) {
        addRubro.addEventListener('click', function () {
            if (!canCreate) return;
            showCreateRubro();
        });
    }
    document.getElementById('account-type').addEventListener('change', function () {
        if (document.getElementById('account-grouping').value === '1') return;
        var next = balanceFromType(this.value);
        if (!next) return;
        document.getElementById('account-balance').value = next;
    });
    document.getElementById('account-balance').addEventListener('change', function () {
        this.dataset.touched = '1';
    });
    document.getElementById('account-remove-open').addEventListener('click', openRemove);
    document.getElementById('remove-cancel').addEventListener('click', function () {
        if (selected) showEdit(selected);
    });
    document.getElementById('account-remove').addEventListener('change', function (event) {
        if (event.target.name !== 'children_action') return;
        document.getElementById('remove-target').disabled = event.target.value === 'delete';
    });

    document.getElementById('account-search').addEventListener('input', function () {
        var query = this.value.trim().toLowerCase();
        var nodes = Array.prototype.slice.call(tree.querySelectorAll('.account-node'));
        if (!query) {
            nodes.forEach(function (node) { node.classList.remove('is-filter-hidden'); });
            return;
        }
        nodes.forEach(function (node) {
            var text = ((node.getAttribute('data-code') || '') + ' ' + (node.getAttribute('data-name') || '')).toLowerCase();
            node.classList.toggle('is-filter-hidden', text.indexOf(query) === -1);
        });
        nodes.forEach(function (node) {
            if (node.querySelector('.account-node:not(.is-filter-hidden)')) {
                node.classList.remove('is-filter-hidden');
                setOpen(node, true);
            }
        });
    });

    if (oldForm && (oldForm.code || oldForm.parent_code)) {
        var anchor = findByCode(oldForm.mode === 'create' ? oldForm.parent_code : oldForm.code) || findByCode(oldForm.code);
        if (anchor) selectNode(anchor, oldForm.mode === 'edit' ? 'edit' : 'create');
        hint.hidden = true;
        context.hidden = false;
        form.hidden = false;
        var restoredGrouping = String(oldForm.is_grouping) === '1';
        var restoredMode = oldForm.mode === 'edit'
            ? 'edit'
            : (restoredGrouping ? (oldForm.parent_code ? 'create-subrubro' : 'create-rubro') : 'create');
        fillForm({
            id: oldForm.mode === 'edit' ? oldForm.id : '',
            parent: oldForm.parent_code,
            code: oldForm.code,
            name: oldForm.name,
            type: oldForm.account_type,
            active: oldForm.is_active,
            grouping: restoredGrouping,
            balance: oldForm.balance_nature || ''
        }, restoredMode);
    } else if (selectedCode) {
        var current = findByCode(selectedCode);
        if (current) selectNode(current, 'edit');
    }
})();
</script>
@endsection
