@foreach ($columns as $column)
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
@endforeach
