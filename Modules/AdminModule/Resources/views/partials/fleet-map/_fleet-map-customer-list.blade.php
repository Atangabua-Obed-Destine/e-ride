@forelse($customers as $customer)
    @php
        $trip = $customer?->customerTrips()?->whereIn('current_status',[ACCEPTED,OUT_FOR_PICKUP, ONGOING])->where('type', RIDE_REQUEST)->first()
    @endphp
    <li class="user-details">
        <label class="form-check" data-id="{{$customer->id}}">
            <img class="form-check-img svg"
                 src="{{ $trip ? dynamicAsset('public/assets/admin-module/img/maps/paper-plane.svg') :dynamicAsset('public/assets/admin-module/img/maps/idle.svg') }}"
                 alt="">
            <div class="form-check-label">
                <div class="d-flex gap-2 align-items-center mb-2">
                    <h5 class="zone-name flex-grow-1 mb-0">{{$customer->full_name ??  ($customer->first_name ? $customer->first_name .' '.$customer->last_name : "N/A" ) }}
                        <div
                            class="badge badge-pill badge-info ms-2">{{ $customer?->customerTrips()?->whereIn('current_status',[ACCEPTED,ONGOING])->first() ? translate("On-Trip") : ""}}</div>
                    </h5>
                    @if($trip?->customerSafetyAlertPending)
                        <div class="flex-shrink-0 position-relative hover-like-tooltip">
                            <img class="svg" src="{{dynamicAsset('public/assets/admin-module/img/svg/shield-red.svg')}}"
                                 alt="">
                            <div class="like-tooltip">
                                @if($trip?->customerSafetyAlertPending?->reason || $trip?->customerSafetyAlertPending?->comment)
                                    @if($trip?->customerSafetyAlertPending?->reason)
                                        @foreach($trip?->customerSafetyAlertPending?->reason as $reason)
                                            {{ $reason }}
                                            <br>
                                        @endforeach
                                    @endif
                                    @if($trip?->customerSafetyAlertPending?->comment)
                                        {{ $trip?->customerSafetyAlertPending?->comment }}
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <div class="w-100">
                        <span>{{translate("phone")}}</span>
                        <span>:</span>
                        <span>{{$customer->phone}}</span>
                    </div>
                    @if($trip)
                        <div>
                            <span>{{translate("Trip ID")}}</span>
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
