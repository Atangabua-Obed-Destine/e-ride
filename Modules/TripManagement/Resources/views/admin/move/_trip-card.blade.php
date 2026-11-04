{{-- Trip details: what an E-Ride Move carries. The price is agreed in person. --}}
@if ($trip->is_move)
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0"><i class="bi bi-truck"></i> {{ translate('E-Ride_Move') }}</h6>
                <span class="badge badge-info">{{ translate('Price_agreed_in_person') }}</span>
            </div>
            @if ($trip->note)
                <div class="fs-12 mb-2">{{ $trip->note }}</div>
            @endif
            @if (!empty($trip->move_photos))
                <div class="d-flex gap-2 flex-wrap">
                    @foreach (app(\Modules\TripManagement\Service\MoveTripService::class)->photoUrls($trip->move_photos) as $url)
                        <a href="{{ $url }}" target="_blank"><img src="{{ $url }}" alt="" width="72" height="72" class="rounded" style="object-fit: cover"></a>
                    @endforeach
                </div>
            @endif
            <div class="fs-12 text-muted mt-2">{{ translate('E-Ride charges only the booking fee. The customer pays the driver the price they agreed.') }}</div>
        </div>
    </div>
@endif
