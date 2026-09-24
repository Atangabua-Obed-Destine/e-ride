@php
    $labelOnTrip = translate('On-Trip');
    $labelPhone = translate('phone');
    $labelTripId = translate('Trip ID');
    $iconPaperPlane = dynamicAsset('public/assets/admin-module/img/maps/paper-plane.svg');
    $iconIdle = dynamicAsset('public/assets/admin-module/img/maps/idle.svg');
    $iconShield = dynamicAsset('public/assets/admin-module/img/svg/shield-red.svg');
@endphp
@forelse($customers as $customer)
    @php
        $trip = $customer?->customerTrips?->first(fn($item) => in_array($item->current_status, [ACCEPTED, OUT_FOR_PICKUP, ONGOING]) && $item->type == RIDE_REQUEST);
        $safetyAlert = $trip?->safetyAlerts->firstWhere('sent_by', $customer->id);
    @endphp
    <li class="user-details">
        <label class="form-check" data-id="{{$customer->id}}">
            <img class="form-check-img svg"
                 src="{{ $trip ? $iconPaperPlane : $iconIdle }}"
                 alt="">
            <div class="form-check-label">
                <div class="d-flex gap-2 align-items-center mb-2">
                    <h5 class="zone-name flex-grow-1 mb-0">{{$customer->full_name ??  ($customer->first_name ? $customer->first_name .' '.$customer->last_name : "N/A" ) }}
                        <div
                            class="badge badge-pill badge-info ms-2">{{ $customer?->customerTrips?->first(fn($item) => in_array($item->current_status, [ACCEPTED, ONGOING])) ? $labelOnTrip : ""}}</div>
                    </h5>
                    @if($safetyAlert)
                        <div class="flex-shrink-0 position-relative hover-like-tooltip">
                            <img class="svg" src="{{$iconShield}}"
                                 alt="">
                            <div class="like-tooltip">
                                @if($safetyAlert?->reason || $safetyAlert?->comment)
                                    @if($safetyAlert?->reason)
                                        @foreach($safetyAlert?->reason as $reason)
                                            {{ $reason }}
                                            <br>
                                        @endforeach
                                    @endif
                                    @if($safetyAlert?->comment)
                                        {{ $safetyAlert?->comment }}
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <div class="w-100">
                        <span>{{$labelPhone}}</span>
                        <span>:</span>
                        <span>{{$customer->phone}}</span>
                    </div>
                    @if($trip)
                        <div>
                            <span>{{$labelTripId}}</span>
                            <span>:</span>
                            <span>
                            {{ $trip->ref_id }}
                            <a href="{{route('admin.trip.show', ['type' => ALL, 'id' => $trip->id, 'page' => 'summary'])}}"
                               target="_blank">
                                <img
                                    src="http://localhost/HexaRide-Admin/public/assets/admin-module/img/maps/up-right-arrow-square.svg"
                                    class="svg" alt="">
                            </a>
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </label>
    </li>
@empty
    <div class="d-flex justify-content-center align-items-center" style="height: 30vh">
        <div class="d-flex flex-column align-items-center gap-20">
            <img width="38" src="{{ dynamicAsset('public/assets/admin-module/img/svg/customer-man.svg') }}" alt="">
            <p class="fs-12">{{ translate('no Customer found') }}</p>
        </div>
    </div>
@endforelse
