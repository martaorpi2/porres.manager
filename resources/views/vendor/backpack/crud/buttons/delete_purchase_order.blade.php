@php
    $blockedByOp = isset($entry->active_payment_orders_count)
        ? (int) $entry->active_payment_orders_count > 0
        : $entry->hasBlockingPaymentOrder();
    $canDelete = ! $blockedByOp && $crud->hasAccess('delete', $entry);
@endphp

@if ($canDelete)
    <a href="javascript:void(0)" onclick="deleteEntry(this)" bp-button="delete" data-route="{{ url($crud->route.'/'.$entry->getKey()) }}" class="btn btn-sm btn-link" data-button-type="delete">
        <i class="la la-trash"></i> <span>{{ trans('backpack::crud.delete') }}</span>
    </a>
@endif

{{-- Button Javascript --}}
{{-- - used right away in AJAX operations (ex: List) --}}
{{-- - pushed to the end of the page, after jQuery is loaded, for non-AJAX operations (ex: Show) --}}
@push('after_scripts') @if (request()->ajax()) @endpush @endif
@bassetBlock('backpack/crud/buttons/delete-purchase-order-button-'.app()->getLocale().'.js')
<script>

	if (typeof deleteEntry != 'function') {
	  $("[data-button-type=delete]").unbind('click');

	  function deleteEntry(button) {
		var route = $(button).attr('data-route');

		swal({
		  title: "{!! trans('backpack::base.warning') !!}",
		  text: "{!! trans('backpack::crud.delete_confirm') !!}",
		  icon: "warning",
		  buttons: {
		  	cancel: {
				text: "{!! trans('backpack::crud.cancel') !!}",
				value: null,
				visible: true,
				className: "bg-secondary",
				closeModal: true,
			},
			delete: {
				text: "{!! trans('backpack::crud.delete') !!}",
				value: true,
				visible: true,
				className: "bg-danger",
				},
			},
		  dangerMode: true,
		}).then((value) => {

				$.ajax({
			      url: route,
			      type: 'DELETE',
			      beforeSend: function(xhr) {
			          var tokenMeta = document.querySelector('meta[name="csrf-token"]');
			          if (tokenMeta && tokenMeta.content) {
			              xhr.setRequestHeader('X-CSRF-TOKEN', tokenMeta.content);
			          }
			      },
			      success: function(result) {
			          if (result == 1) {
						  if (typeof crud != 'undefined' && typeof crud.table != 'undefined') {
							  if(crud.table.rows().count() === 1) {
							    crud.table.page("previous");
							  }

							  crud.table.draw(false);
						  }

			              new Noty({
		                    type: "success",
		                    text: "{!! '<strong>'.trans('backpack::crud.delete_confirmation_title').'</strong><br>'.trans('backpack::crud.delete_confirmation_message') !!}"
		                  }).show();

			              $('.modal').modal('hide');
			          } else {
			          	  if (result instanceof Object) {
			          	  	Object.entries(result).forEach(function(entry, index) {
			          	  	  var type = entry[0];
			          	  	  entry[1].forEach(function(message, i) {
					          	  new Noty({
				                    type: type,
				                    text: message
				                  }).show();
			          	  	  });
			          	  	});
			          	  } else {
				              swal({
				              	title: "{!! trans('backpack::crud.delete_confirmation_not_title') !!}",
	                            text: "{!! trans('backpack::crud.delete_confirmation_not_message') !!}",
				              	icon: "error",
				              	timer: 4000,
				              	buttons: false,
				              });
			          	  }
			          }
			      },
			      error: function(result) {
			          swal({
		              	title: "{!! trans('backpack::crud.delete_confirmation_not_title') !!}",
                        text: "{!! trans('backpack::crud.delete_confirmation_not_message') !!}",
		              	icon: "error",
		              	timer: 4000,
		              	buttons: false,
		              });
			      }
			  });
			}
		});

      }
	}

</script>
@endBassetBlock
@if (!request()->ajax()) @endpush @endif
