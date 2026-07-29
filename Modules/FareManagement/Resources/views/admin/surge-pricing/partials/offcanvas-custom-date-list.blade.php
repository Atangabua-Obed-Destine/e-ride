<div class="offcanvas-header">
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
</div>
@if(isset($isDetailsPage))
    <div class="offcanvas-body scrollbar-thin">
        <div class="mb-30 text-center">
            <h4 class="mb-1 fw-semibold">{{ translate('Selected Days List') }}</h4>
            <p class="mb-0">{{ translate('This surge price will be applied in the days below') }}</p>
        </div>
        <div>
            <div class="d-flex justify-content-between align-items-center gap-3 mb-10px">
                <h6 class="fs-12 opacity-60 mb-0">{{ translate('Date') }}</h6>
                <h6 class="fs-12 opacity-60 mb-0">{{ translate('TIme') }}</h6>
            </div>
        </div>

        <div class="d-flex flex-column gap-2">
            @foreach($data['date_time_slots'] as $slot)
                <div
                    class="bg-light p-3 rounded d-flex justify-content-between align-items-center flex-wrap gap-3 w-100 text-dark">
                    <span>{{ $slot['date'] }}</span>
                    <span class=" d-flex align-items-center gap-2">
                        <span>{{ $slot['time_slot'] }}</span>
                        <i class="bi bi-clock"></i>
                    </span>
                </div>
            @endforeach
        </div>
    </div>
@else
    <div class="offcanvas-body scrollbar-thin">
        <h3 class="mb-1">{{ translate('Custom Date List') }}</h3>
        <p>{{ translate('Surge Price is activated in this time slots.') }}</p>

        <div class="bg-light mt-3 rounded d-flex flex-wrap gap-2 p-3">
            <div class="bg-white p-3 rounded d-flex flex-column gap-4 w-100 text-dark">
                @foreach($data['date_time_slots'] as $slot)
                    <div class="d-flex gap-3 gap-sm-5">
                        <span>{{ $slot['date'] }}</span>
                        <span class="opacity-75 ltr">{{ $slot['time_slot'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif


