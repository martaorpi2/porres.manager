@extends(backpack_view('blank'))

@push('after_styles')
  @basset('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.6.0/styles/base16/dracula.min.css')
@endpush

@push('after_scripts')
  @basset('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.6.0/highlight.min.js')
  <script>hljs.highlightAll();</script>
@endpush

@section('content')
<div class="card">
  <div class="card-body">
    <h3></h3>
    
  </div>
</div>

@endsection
