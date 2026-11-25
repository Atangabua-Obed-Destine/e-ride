{{-- The form's validation refusals, in one block above it (the frame shows flash messages as toasts, not these). --}}
@if($errors->any())
<div class="alert alert-danger" role="alert">
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $e)
        <li>{{ $e }}</li>
        @endforeach
    </ul>
</div>
@endif
