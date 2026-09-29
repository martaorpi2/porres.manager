@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="mb-0" bp-section="page-heading">Libro diario</h1>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body">
                <form method="get" action="{{ backpack_url('accounting-journal') }}" class="row g-2 align-items-end">
                    <div class="col-sm-6 col-md-2">
                        <label for="from" class="form-label">Desde</label>
                        <input type="date" name="from" id="from" class="form-control" value="{{ $filters['from'] }}">
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label for="to" class="form-label">Hasta</label>
                        <input type="date" name="to" id="to" class="form-control" value="{{ $filters['to'] }}">
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <label for="kind" class="form-label">Tipo</label>
                        <select name="kind" id="kind" class="form-control">
                            <option value="">Todos</option>
                            @foreach($kinds as $value => $label)
                                <option value="{{ $value }}" @selected($filters['kind'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <label for="account_id" class="form-label">Cuenta</label>
                        <select name="account_id" id="account_id" class="form-control">
                            <option value="">Todas</option>
                            @foreach($accounts as $id => $label)
                                <option value="{{ $id }}" @selected((int) $filters['account_id'] === (int) $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-12 col-md-2">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="{{ backpack_url('accounting-journal') }}" class="btn btn-secondary">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body table-responsive p-0">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Asiento</th>
                            <th>Descripción</th>
                            <th>Tipo</th>
                            <th class="text-end">Debe</th>
                            <th class="text-end">Haber</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            <tr>
                                <td>{{ $entry->date?->format('d/m/Y') }}</td>
                                <td>{{ $entry->entry_number }}</td>
                                <td>{{ $entry->description }}</td>
                                <td>{{ $entry->kind_label }}</td>
                                <td class="text-end">{{ number_format((float) $entry->debit_total, 2, ',', '.') }}</td>
                                <td class="text-end">{{ number_format((float) $entry->credit_total, 2, ',', '.') }}</td>
                                <td class="text-end">
                                    <a href="{{ backpack_url('accounting-journal/'.$entry->id) }}" class="btn btn-sm btn-link">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-muted">No hay asientos para ese filtro.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($entries->hasPages())
                <div class="card-footer">{{ $entries->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
