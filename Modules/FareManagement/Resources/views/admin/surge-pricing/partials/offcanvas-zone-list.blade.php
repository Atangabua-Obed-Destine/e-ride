<div class="offcanvas-header">
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
            aria-label="Close"></button>
</div>
<div class="offcanvas-body scrollbar-thin">
    <h3 class="mb-1">{{ translate('Zone List') }}</h3>
    <p>{{ translate('Surge Price is activated in this zones') }}.</p>

    <div class="bg-light mt-3 rounded d-flex flex-wrap gap-2 p-3">
        @foreach($zones as $zone)
            <span class="badge badge-primary">{{ $zone }}</span>
        @endforeach
    </div>
</div>
