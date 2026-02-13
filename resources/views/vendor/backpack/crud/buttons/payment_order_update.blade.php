@if ($entry->status !== 'Anulada' && $crud->hasAccess('update', $entry))
	<a href="{{ url($crud->route.'/'.$entry->getKey().'/edit') }}" bp-button="update" class="btn btn-sm btn-link">
		<i class="la la-edit"></i> <span>{{ trans('backpack::crud.edit') }}</span>
	</a>
@endif
