@php
    $columnsWithoutLabel = $columnsWithoutLabel ?? [];
@endphp
@foreach ($columns as $column)
    @php
        $columnName = $column['name'] ?? '';
        $fullWidth = in_array($columnName, $columnsWithoutLabel, true);
    @endphp
    @if ($fullWidth)
        <tr>
            <td colspan="2" @if ($loop->first) class="border-top-0 p-0" @else class="p-0" @endif>
                @php
                    $columnPaths = array_map(function ($item) use ($column) {
                        return $item.'.'.$column['type'];
                    }, \Backpack\CRUD\ViewNamespaces::getFor('columns'));

                    if (! in_array('crud::columns.text', $columnPaths)) {
                        $columnPaths[] = 'crud::columns.text';
                    }
                @endphp
                @includeFirst($columnPaths)
            </td>
        </tr>
    @else
        <tr>
            <td @if ($loop->first) class="border-top-0" @endif>
                <strong>{!! $column['label'] !!}@if (! empty($column['label'])):@endif</strong>
            </td>
            <td @if ($loop->first) class="border-top-0" @endif>
                @php
                    $columnPaths = array_map(function ($item) use ($column) {
                        return $item.'.'.$column['type'];
                    }, \Backpack\CRUD\ViewNamespaces::getFor('columns'));

                    if (! in_array('crud::columns.text', $columnPaths)) {
                        $columnPaths[] = 'crud::columns.text';
                    }
                @endphp
                @includeFirst($columnPaths)
            </td>
        </tr>
    @endif
@endforeach
