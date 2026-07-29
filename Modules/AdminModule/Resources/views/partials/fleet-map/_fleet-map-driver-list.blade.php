@forelse($drivers as $driver)
    @php
        $trip = $driver?->driverTrips()?->whereIn('current_status',[ACCEPTED, OUT_FOR_PICKUP, ONGOING])->where('type', RIDE_REQUEST)->first()
    @endphp
    <li class="user-details">
        <label class="form-check" data-id="{{$driver->id}}">
            <img class="form-check-img svg"
                 src="{{$trip ? dynamicAsset('public/assets/admin-module/img/maps/paper-plane.svg') :dynamicAsset('public/assets/admin-module/img/maps/idle.svg') }}"
                 alt="">
            <div class="form-check-label">
                <div class="d-flex gap-2 align-items-center mb-2">
                    <h5 class="zone-name flex-grow-1 mb-0">{{$driver->full_name ??  ($driver->first_name ? $driver->first_name .' '.$driver->last_name : "N/A" ) }}
                        <span
                            class="badge badge-info">{{Carbon\Carbon::parse($driver->created_at)->diffInMonths(Carbon\Carbon::now())<6 ? translate("New") : ""}}</span>
                    </h5>
                    @if($trip?->driverSafetyAlertPending)
                        <div class="flex-shrink-0 position-relative hover-like-tooltip">
                            <img class="svg" src="{{dynamicAsset('public/assets/admin-module/img/svg/shield-red.svg')}}"
                                 alt="">
                            <div class="like-tooltip">
                                @if($trip?->driverSafetyAlertPending?->reason || $trip?->driverSafetyAlertPending?->comment)
                                    @if($trip?->driverSafetyAlertPending?->reason)
                                        @foreach($trip?->driverSafetyAlertPending?->reason as $reason)
                                            {{ $reason }}
                                            <br>
                                        @endforeach
                                    @endif
                                    @if($trip?->driverSafetyAlertPending?->comment)
                                        {{ $trip?->driverSafetyAlertPending?->comment }}
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
                        <span>{{$driver->phone}}</span>
                    </div>
                    <div>
                        <span>{{translate("Vehicle No")}}</span>
                        <span>:</span>
                        <span>{{$driver?->vehicle?->licence_plate_number ?? "N/A"}}</span>
                    </div>
                    <span class="fs-8">|</span>
                    <div>
                        <span>{{translate("Model")}}</span>
                        <span>:</span>
                        <span>{{$driver?->vehicle?->model?->name ?? "N/A"}}</span>
                    </div>
                </div>
            </div>
        </label>
    </li>
@empty
    <div class="d-flex justify-content-center align-items-center" style="height: 30vh">
        <div class="d-flex flex-column align-items-center gap-20">
            <img width="38" src="{{ dynamicAsset('public/assets/admin-module/img/svg/driver-man.svg') }}" alt="">
            <p class="fs-12">{{ translate('no driver found') }}</p>
        </div>
    </div>
@endforelse
